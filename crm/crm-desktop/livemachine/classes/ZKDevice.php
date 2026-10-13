<?php
declare(strict_types=1);

/**
 * Hand-rolled ZKTeco / ESSL protocol driver over TCP.
 *
 *  WIRE FORMAT
 *  -----------
 *  Every TCP packet is framed by an 8-byte header:
 *      bytes 0-3 : magic 0x50 0x50 0x82 0x7d
 *      bytes 4-7 : payload size, little-endian uint32
 *
 *  Each payload starts with an 8-byte command header:
 *      bytes 0-1 : command  (LE16)   — see CMD_* constants
 *      bytes 2-3 : checksum (LE16)   — ones'-complement of 16-bit sum
 *      bytes 4-5 : session_id (LE16) — assigned by device on first reply
 *      bytes 6-7 : reply_id   (LE16) — increments per request
 *      bytes 8…  : optional data
 *
 *  HANDSHAKE
 *  ---------
 *      1. CMD_CONNECT  → device replies CMD_ACK_OK (no password) or
 *                        CMD_ACK_UNAUTH (password set). session_id is in reply.
 *      2. If UNAUTH    → CMD_AUTH with comm_key transform sent as data.
 *      3. Device must answer CMD_ACK_OK before any further command works.
 *
 *  BULK FETCH (e.g. attendance log)
 *  --------------------------------
 *      1. CMD_DATA_WRRQ with packed sub-command payload.
 *      2. Device replies CMD_PREPARE_DATA (length) then streams CMD_DATA
 *         frames, terminated by CMD_ACK_OK.
 */
final class ZKDevice
{
    // Framing
    public const ZK_MAGIC = "\x50\x50\x82\x7d";

    // Core commands used in this driver
    public const CMD_CONNECT       = 1000;
    public const CMD_EXIT          = 1001;
    public const CMD_ENABLE_DEVICE = 1002;
    public const CMD_DISABLE_DEVICE= 1003;
    public const CMD_RESTART       = 1004;
    public const CMD_GET_TIME      = 201;
    public const CMD_SET_TIME      = 202;
    public const CMD_GET_FREE_SIZES= 50;
    public const CMD_USER_WRQ      = 8;    // write/upsert user
    public const CMD_USERTEMP_RRQ  = 9;    // read fingerprint template
    public const CMD_DELETE_USER   = 18;
    public const CMD_ATTLOG_RRQ    = 13;
    // Function codes for CMD_DATA_WRRQ envelope
    public const FCT_ATTLOG        = 1;
    public const FCT_FINGERTMP     = 2;
    public const FCT_USER          = 5;
    public const CMD_CLEAR_DATA    = 14;   // clear ALL data
    public const CMD_CLEAR_ATTLOG  = 15;   // clear attendance logs only
    public const CMD_ACK_OK        = 2000;
    public const CMD_ACK_ERROR     = 2001;
    public const CMD_ACK_DATA      = 2002;
    public const CMD_ACK_UNAUTH    = 2005;
    public const CMD_PREPARE_DATA  = 1500;
    public const CMD_DATA          = 1501;
    public const CMD_FREE_DATA     = 1502;
    public const CMD_DATA_WRRQ     = 1503; // bulk read by sub-command
    public const CMD_DATA_RDY      = 1504;
    public const CMD_AUTH          = 1102;
    public const CMD_GET_VERSION   = 1100;
    public const CMD_DEVICE        = 11;   // device info / parameter query

    private ?\Socket $socket = null; // unused (we use stream)
    /** @var resource|null */
    private $stream = null;

    private int $sessionId = 0;
    private int $replyId   = 0;
    private int $timeoutSec;

    public function __construct(
        private string $ip,
        private int    $port     = 4370,
        private int    $commKey  = 0,
        int            $timeout  = 8,
        private ?string $logPath = null,
    ) {
        $this->timeoutSec = $timeout;
    }

    // ───────────────────────── Connection lifecycle ──────────────────────

    public function connect(): bool
    {
        $errno = 0; $errstr = '';
        $this->stream = @stream_socket_client(
            "tcp://{$this->ip}:{$this->port}",
            $errno, $errstr,
            (float)$this->timeoutSec
        );
        if (!$this->stream) {
            $this->log("connect() failed: [{$errno}] {$errstr}");
            return false;
        }
        stream_set_timeout($this->stream, $this->timeoutSec);

        $resp = $this->send(self::CMD_CONNECT);
        if ($resp === null) {
            $this->log('connect: no response');
            return false;
        }

        // CMD_ACK_UNAUTH carries the freshly-allocated session_id; CMD_AUTH must echo it.
        if ($resp['cmd'] === self::CMD_ACK_UNAUTH) {
            $this->log('device requires comm_key — authenticating');
            return $this->authenticate();
        }
        if ($resp['cmd'] !== self::CMD_ACK_OK) {
            $this->log("connect: unexpected cmd={$resp['cmd']}");
            return false;
        }
        return true;
    }

    /**
     * ZKTeco comm_key transform:
     *   key32 = key << 16 | 0x8000_0000
     *   Then XOR each byte of an ASCII-encoded "ZKSO" stamp with key bytes.
     *   The reference implementation (pyzk / fwhibas):
     *     k = 0
     *     for i in range(32):
     *         if (commkey & (1 << i)) != 0:
     *             k = (k << 1) | 1
     *         else:
     *             k = k << 1
     *         k &= 0xFFFFFFFF
     *     k = k ^ session_id
     *     k = ( (k & 0xFF) , (k>>8 & 0xFF) , (k>>16 & 0xFF) , (k>>24 & 0xFF) )
     *     k = k ^ "ZKSO"  (byte-wise)
     *     k = (k[1], k[0], k[3], k[2])        // word-swap
     *     k = ( k[i] ^ (date_t low byte) … )   -- simplified: many firmwares accept the above
     */
    private function authenticate(): bool
    {
        $key = self::commKeyHash($this->commKey, $this->sessionId);
        $resp = $this->send(self::CMD_AUTH, $key);
        if ($resp === null || $resp['cmd'] !== self::CMD_ACK_OK) {
            $this->log('authenticate: rejected (wrong comm_key?)');
            return false;
        }
        return true;
    }

    /**
     * Reference comm_key hashing routine (pyzk canonical, verified against
     * live device 192.168.15.76 with key=0).
     *
     * Steps:
     *   1. Bit-reverse the 32-bit comm_key value.
     *   2. XOR with session_id.
     *   3. Take the 4 LE bytes (b0,b1,b2,b3).
     *   4. XOR with stamp "ZKSO" (Z=b0, K=b1, S=b2, O=b3).
     *   5. Word-swap: (b0,b1,b2,b3) -> (b2,b3,b0,b1).
     *   6. Apply ticks: (b0^t, b1^t, t, b3^t) — byte 2 is *replaced* with t.
     *
     * The `ticks` byte is a per-firmware constant. 50 is the documented
     * pyzk default and is accepted by this device.
     */
    public static function commKeyHash(int $commKey, int $sessionId, int $ticks = 50): string
    {
        $k = 0;
        for ($i = 0; $i < 32; $i++) {
            $k = ($commKey & (1 << $i)) ? (($k << 1) | 1) : ($k << 1);
            $k &= 0xFFFFFFFF;
        }
        $k ^= $sessionId & 0xFFFFFFFF;

        $b = [$k & 0xFF, ($k >> 8) & 0xFF, ($k >> 16) & 0xFF, ($k >> 24) & 0xFF];

        // XOR with "ZKSO" stamp
        $b[0] ^= ord('Z'); $b[1] ^= ord('K'); $b[2] ^= ord('S'); $b[3] ^= ord('O');

        // Word swap: (b0,b1,b2,b3) -> (b2,b3,b0,b1)
        [$b[0], $b[1], $b[2], $b[3]] = [$b[2], $b[3], $b[0], $b[1]];

        // Ticks transform
        $t = $ticks & 0xFF;
        return chr($b[0] ^ $t) . chr($b[1] ^ $t) . chr($t) . chr($b[3] ^ $t);
    }

    public function disconnect(): void
    {
        if ($this->stream) {
            try { $this->send(self::CMD_EXIT); } catch (\Throwable) { /* best effort */ }
            @fclose($this->stream);
            $this->stream = null;
        }
        $this->sessionId = 0;
        $this->replyId   = 0;
    }

    // ─────────────────────────── Quick queries ───────────────────────────

    public function ping(): bool
    {
        $errno = 0; $errstr = '';
        $s = @stream_socket_client("tcp://{$this->ip}:{$this->port}", $errno, $errstr, 3.0);
        if (!$s) return false;
        @fclose($s);
        return true;
    }

    public function getDeviceTime(): ?string
    {
        $resp = $this->send(self::CMD_GET_TIME);
        if ($resp === null || $resp['cmd'] !== self::CMD_ACK_OK || strlen($resp['data']) < 4) {
            return null;
        }
        $enc = unpack('V', substr($resp['data'], 0, 4))[1];
        return self::decodeTime($enc);
    }

    public function setDeviceTime(\DateTimeInterface $when): bool
    {
        $enc  = self::encodeTime($when);
        $resp = $this->send(self::CMD_SET_TIME, pack('V', $enc));
        return $resp !== null && $resp['cmd'] === self::CMD_ACK_OK;
    }

    public function restart(): bool
    {
        $resp = $this->send(self::CMD_RESTART);
        return $resp !== null && $resp['cmd'] === self::CMD_ACK_OK;
    }

    public function clearAttendanceLogs(): bool
    {
        $resp = $this->send(self::CMD_CLEAR_ATTLOG);
        return $resp !== null && $resp['cmd'] === self::CMD_ACK_OK;
    }

    public function enable(): bool
    {
        $resp = $this->send(self::CMD_ENABLE_DEVICE);
        return $resp !== null && $resp['cmd'] === self::CMD_ACK_OK;
    }

    public function disable(): bool
    {
        $resp = $this->send(self::CMD_DISABLE_DEVICE);
        return $resp !== null && $resp['cmd'] === self::CMD_ACK_OK;
    }

    // ───────────────────────────── Users CRUD ────────────────────────────

    /**
     * @return array<int,array{uid:int,user_id:string,name:string,role:int,password:string,card_no:int}>
     */
    public function getUsers(): array
    {
        $raw = $this->readWithBuffer(self::CMD_USERTEMP_RRQ, self::FCT_USER);
        if ($raw === null || strlen($raw) < 4) return [];

        // First 4 bytes are total size (LE32); records follow.
        $total  = unpack('V', substr($raw, 0, 4))[1];
        $stream = substr($raw, 4);
        $size   = (int)$total;

        // Each user record is 72 bytes:
        //   uid:H2, role:C, password:8, name:24, card_no:V, group:C, reserved:H2, user_id:9
        $recordLen = 72;
        $out = [];
        for ($p = 0; $p + $recordLen <= strlen($stream); $p += $recordLen) {
            $rec = substr($stream, $p, $recordLen);
            $uid     = unpack('v', substr($rec, 0, 2))[1];
            $role    = ord($rec[2]);
            $password= rtrim(substr($rec, 3, 8), "\x00");
            $name    = rtrim(self::decodeString(substr($rec, 11, 24)), "\x00");
            $card    = unpack('V', substr($rec, 35, 4))[1];
            $user_id = rtrim(substr($rec, 48, 9), "\x00");

            $out[] = [
                'uid'      => $uid,
                'user_id'  => $user_id !== '' ? $user_id : (string)$uid,
                'name'     => $name,
                'role'     => $role,
                'password' => $password,
                'card_no'  => $card,
            ];
        }
        return $out;
    }

    /**
     * Upsert a user on the device.
     *
     * Tries the modern ZK8 72-byte record first (required by current firmwares
     * including K40 / SpeedFace / most 2018+ devices) and falls back to the
     * legacy 51-byte record for older units. The device must be DISABLED
     * during user writes on many firmwares, so we wrap the call.
     *
     * 72-byte layout (little-endian, matches pyzk user_packet_size=72):
     *   <H>   uid               (2)
     *   <B>   privilege         (1)   0 = user, 14 = admin
     *   8s    password          (8)
     *   24s   name              (24)
     *   <I>   card_no           (4)
     *   <B>   reserved=0        (1)
     *   7s    group_id          (7)
     *   x     padding           (1)
     *   24s   user_id (string)  (24)
     *   ──────────────────────  72 bytes total
     */
    public function setUser(int $uid, string $userId, string $name, int $role = 0, string $password = '', int $cardNo = 0): bool
    {
        $nameEnc  = substr(self::encodeString($name), 0, 24);
        $passEnc  = substr($password, 0, 8);

        $this->disable();
        try {
            $rec72 = pack('v', $uid)
                   . chr($role & 0xFF)
                   . str_pad($passEnc, 8, "\x00")
                   . str_pad($nameEnc, 24, "\x00")
                   . pack('V', $cardNo)
                   . chr(0)
                   . str_pad('', 7, "\x00")
                   . "\x00"
                   . str_pad(substr($userId, 0, 24), 24, "\x00");

            if (strlen($rec72) === 72) {
                $resp = $this->send(self::CMD_USER_WRQ, $rec72);
                if ($resp !== null && $resp['cmd'] === self::CMD_ACK_OK) {
                    return true;
                }
                $this->log(sprintf(
                    'setUser 72-byte rejected (reply cmd=%s) — trying legacy 51-byte',
                    $resp['cmd'] ?? 'null'
                ));
            }

            // Legacy 51-byte record for older firmwares.
            $rec51 = pack('v', $uid)
                   . chr($role)
                   . str_pad($passEnc, 8, "\x00")
                   . str_pad($nameEnc, 24, "\x00")
                   . pack('V', $cardNo)
                   . chr(0)
                   . "\x00\x00"
                   . str_pad(substr($userId, 0, 9), 9, "\x00");

            $resp = $this->send(self::CMD_USER_WRQ, $rec51);
            return $resp !== null && $resp['cmd'] === self::CMD_ACK_OK;
        } finally {
            $this->enable();
        }
    }

    public function deleteUser(int $uid): bool
    {
        $resp = $this->send(self::CMD_DELETE_USER, pack('v', $uid) . "\x00\x00");
        return $resp !== null && $resp['cmd'] === self::CMD_ACK_OK;
    }

    // ───────────────────────── Attendance logs ───────────────────────────

    /**
     * @return array<int,array{uid:int,user_id:string,timestamp:string,status:int,type:int}>
     */
    public function getAttendanceLogs(): array
    {
        // Most firmwares answer CMD_ATTLOG_RRQ directly with CMD_PREPARE_DATA → DATA → ACK_OK.
        // The envelope variant returns garbage on this device, so use the direct path here.
        $raw = $this->readBulkDirect(self::CMD_ATTLOG_RRQ);
        if ($raw === null || strlen($raw) < 4) return [];

        $stream = substr($raw, 4);

        // Each attendance record is 40 bytes:
        //   uid:H2, user_id:24, status:C, timestamp:V, type:C, reserved...
        $recordLen = 40;
        $out = [];
        for ($p = 0; $p + $recordLen <= strlen($stream); $p += $recordLen) {
            $rec = substr($stream, $p, $recordLen);
            $uid       = unpack('v', substr($rec, 0, 2))[1];
            $user_id   = rtrim(substr($rec, 2, 24), "\x00");
            $status    = ord($rec[26]);
            $timestamp = unpack('V', substr($rec, 27, 4))[1];
            $type      = ord($rec[31]);

            $out[] = [
                'uid'       => $uid,
                'user_id'   => $user_id !== '' ? $user_id : (string)$uid,
                'timestamp' => self::decodeTime($timestamp),
                'status'    => $status,
                'type'      => $type,
            ];
        }
        return $out;
    }

    // ─────────────────────────── Bulk read engine ────────────────────────

    /**
     * Sends a bulk-data command (CMD_DATA_WRRQ envelope) and reassembles
     * the streamed reply.
     *
     * The 11-byte envelope payload is:
     *   byte  0    : 0x01
     *   bytes 1-2  : sub-command (LE16)   — e.g. CMD_USERTEMP_RRQ or CMD_ATTLOG_RRQ
     *   bytes 3-6  : function code (LE32) — FCT_USER / FCT_ATTLOG / FCT_FINGERTMP
     *   bytes 7-10 : extension (LE32)     — usually 0
     *
     * Two firmware paths are supported:
     *  (a) CMD_DATA returned inline (small payloads <= ~1 KB)
     *  (b) CMD_ACK_OK with 1+4 bytes (status + total size), then CMD_DATA frames stream
     */
    private function readWithBuffer(int $subCmd, int $fct = 0, int $ext = 0): ?string
    {
        $payload  = chr(0x01)
                  . pack('v', $subCmd)
                  . pack('V', $fct)
                  . pack('V', $ext);

        $first = $this->send(self::CMD_DATA_WRRQ, $payload);
        if ($first === null) return null;

        if ($first['cmd'] === self::CMD_DATA) {
            return $first['data'];
        }
        if ($first['cmd'] === self::CMD_ACK_OK) {
            // Some firmwares embed the entire dataset in the ACK payload
            // beyond the leading status byte + size word. If non-empty,
            // return it; otherwise drain follow-up CMD_DATA frames.
            if (strlen($first['data']) > 5) {
                return substr($first['data'], 5);
            }
            return $this->drainDataStream();
        }
        if ($first['cmd'] === self::CMD_PREPARE_DATA) {
            return $this->drainDataStream();
        }
        $this->log("readWithBuffer: unexpected first cmd={$first['cmd']}");
        return null;
    }

    /**
     * Direct bulk-read path: send the read command itself (no CMD_DATA_WRRQ
     * envelope) and drain the resulting CMD_PREPARE_DATA → CMD_DATA → CMD_ACK_OK
     * stream. Used for CMD_ATTLOG_RRQ on firmwares that don't honor the
     * envelope wrapper for attendance.
     */
    private function readBulkDirect(int $cmd): ?string
    {
        $first = $this->send($cmd);
        if ($first === null) return null;

        if ($first['cmd'] === self::CMD_PREPARE_DATA) {
            return $this->drainDataStream();
        }
        if ($first['cmd'] === self::CMD_DATA) {
            return $first['data'];
        }
        if ($first['cmd'] === self::CMD_ACK_OK && strlen($first['data']) > 4) {
            return $first['data'];
        }
        $this->log("readBulkDirect: unexpected first cmd={$first['cmd']}");
        return null;
    }

    private function drainDataStream(): ?string
    {
        $buf = '';
        while (true) {
            $frame = $this->recvFrame();
            if ($frame === null) return null;
            if ($frame['cmd'] === self::CMD_DATA) {
                $buf .= $frame['data'];
                continue;
            }
            if ($frame['cmd'] === self::CMD_ACK_OK) {
                break;
            }
            if ($frame['cmd'] === self::CMD_PREPARE_DATA) {
                continue; // header frame — ignore, payload follows
            }
            $this->log("drainDataStream: unexpected cmd={$frame['cmd']}");
            break;
        }
        return $buf;
    }

    // ─────────────────────────── Wire I/O ────────────────────────────────

    /**
     * @return array{cmd:int,session:int,reply:int,data:string}|null
     */
    private function send(int $cmd, string $data = ''): ?array
    {
        if (!$this->stream) {
            $this->log('send(): no stream open');
            return null;
        }
        $this->replyId = ($this->replyId + 1) & 0xFFFF;

        $payload = pack('v', $cmd) . "\x00\x00"
                 . pack('v', $this->sessionId)
                 . pack('v', $this->replyId)
                 . $data;

        $chk = self::checksum($payload);
        $payload[2] = chr($chk & 0xFF);
        $payload[3] = chr(($chk >> 8) & 0xFF);

        $frame = self::ZK_MAGIC . pack('V', strlen($payload)) . $payload;

        if (@fwrite($this->stream, $frame) === false) {
            $this->log('send(): write failed');
            return null;
        }
        return $this->recvFrame();
    }

    /**
     * @return array{cmd:int,session:int,reply:int,data:string}|null
     */
    private function recvFrame(): ?array
    {
        if (!$this->stream) return null;

        $hdr = $this->readBytes(8);
        if ($hdr === null || strlen($hdr) < 8 || substr($hdr, 0, 4) !== self::ZK_MAGIC) {
            $this->log('recvFrame: bad magic');
            return null;
        }
        $payloadLen = unpack('V', substr($hdr, 4, 4))[1];
        if ($payloadLen < 8) return null;

        $payload = $this->readBytes($payloadLen);
        if ($payload === null || strlen($payload) < 8) return null;

        $cmd     = unpack('v', substr($payload, 0, 2))[1];
        $session = unpack('v', substr($payload, 4, 2))[1];
        $reply   = unpack('v', substr($payload, 6, 2))[1];
        $data    = substr($payload, 8);

        // Latch session_id from device's first reply
        if ($this->sessionId === 0 && $session !== 0) {
            $this->sessionId = $session;
        }

        return ['cmd' => $cmd, 'session' => $session, 'reply' => $reply, 'data' => $data];
    }

    private function readBytes(int $n): ?string
    {
        $buf = '';
        $deadline = microtime(true) + $this->timeoutSec;
        while (strlen($buf) < $n) {
            if (microtime(true) > $deadline) return null;
            $chunk = @fread($this->stream, $n - strlen($buf));
            if ($chunk === false || $chunk === '') {
                $meta = @stream_get_meta_data($this->stream);
                if (!empty($meta['timed_out']) || !empty($meta['eof'])) return null;
                continue;
            }
            $buf .= $chunk;
        }
        return $buf;
    }

    private static function checksum(string $buf): int
    {
        $sum = 0;
        $len = strlen($buf);
        for ($i = 0; $i + 1 < $len; $i += 2) {
            $sum += ord($buf[$i]) | (ord($buf[$i + 1]) << 8);
        }
        if ($len & 1) {
            $sum += ord($buf[$len - 1]);
        }
        $sum = ($sum >> 16) + ($sum & 0xFFFF);
        $sum += ($sum >> 16);
        return (~$sum) & 0xFFFF;
    }

    // ───────────────────────── Time codec (ZK epoch) ─────────────────────

    /**
     * ZK device packs time as a single 32-bit integer:
     *   ((year - 2000) * 12 * 31 + (month-1) * 31 + (day-1)) * 24 * 60 * 60
     *   + hour * 3600 + minute * 60 + second
     */
    public static function decodeTime(int $enc): string
    {
        $second = $enc % 60;            $enc = intdiv($enc, 60);
        $minute = $enc % 60;            $enc = intdiv($enc, 60);
        $hour   = $enc % 24;            $enc = intdiv($enc, 24);
        $day    = ($enc % 31) + 1;      $enc = intdiv($enc, 31);
        $month  = ($enc % 12) + 1;      $enc = intdiv($enc, 12);
        $year   = $enc + 2000;
        return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second);
    }

    public static function encodeTime(\DateTimeInterface $dt): int
    {
        $y = (int)$dt->format('Y') - 2000;
        $m = (int)$dt->format('n') - 1;
        $d = (int)$dt->format('j') - 1;
        $h = (int)$dt->format('G');
        $i = (int)$dt->format('i');
        $s = (int)$dt->format('s');
        return ((($y * 12 * 31) + ($m * 31) + $d) * 24 * 60 * 60) + ($h * 3600) + ($i * 60) + $s;
    }

    // ───────────────────────── String helpers ────────────────────────────

    private static function decodeString(string $raw): string
    {
        // Device names are usually GBK or UTF-8. Try UTF-8 first; fall back to GBK.
        if (mb_check_encoding($raw, 'UTF-8')) return $raw;
        $conv = @iconv('GBK', 'UTF-8//IGNORE', $raw);
        return $conv !== false ? $conv : $raw;
    }

    private static function encodeString(string $s): string
    {
        return mb_convert_encoding($s, 'UTF-8', 'auto');
    }

    // ──────────────────────────── Logging ────────────────────────────────

    private function log(string $msg): void
    {
        if ($this->logPath === null) return;
        $line = sprintf('[%s] %s%s', date('Y-m-d H:i:s'), $msg, PHP_EOL);
        @file_put_contents($this->logPath, $line, FILE_APPEND);
    }
}
