<?php
/* =============================================================================
   SPS CRM THEME ENGINE · v2 — whole-app design system
   -----------------------------------------------------------------------------
   One token layer + one generated stylesheet drives the complete layout of
   the entire CRM (shell, sidebar, header, cards, tables, forms, modals,
   pickers, toasts, automation + HRM surfaces, scrollbars, cursor, motion).

   Config lives in settings_marker (keys crm_theme_*). Written by
   System Settings ▸ Appearance.

   Presets  — every preset has its own sidebar + header layout (see the
              per-preset layout engine in the stylesheet below):
      classic     Today's look (safe default)
      sleek       NEW user-first theme: airy, calm, high-contrast, floating card sidebar
      modern      Clean light · refined borders · soft lifts
      executive   Realistic · deep navy sidebar · warm paper · gold accent
      night       Eye-care dark (warm, dimmed, low blue light)
      midnight    Cool dark · blue-grey surfaces · cyan glow
      aurora      Dark glass · translucent blurred surfaces · moving gradient
      neon        Advanced dark · neon glow, gradient focus, glow nav
      custom      Full designer mode — every token user-controlled

   Advanced (global) controls
     accent      9 native swatch hexes or any custom hex (auto-scaled shades)
     radius      default | square | rounded
     density     standard | compact
     text size   normal | large
     effects     cursor (dot/ring/aura/cross/comet/pulse) + hover micro-interactions + motion
     sidebar     auto (preset) | light | dark
     header      auto (preset) | light | dark | transparent
     glow        extra shadow/glow layer (off/on)
     surfaces    card_bg · sidebar_bg_c · header_bg_c — empty = auto-adapt
                 to the active theme (custom hex overrides on top)

   Class contract (boot script attaches to <body>):
     sps-root · sps-p-<preset> · sps-bg-dark · sps-glass · sps-glow
     sps-radius-square/rounded · sps-compact · sps-font-lg
     sps-effects · sps-motion · sps-cursor-dot|ring|aura

   Public API (unchanged, so header.php keeps working)
     sps_theme_config($con)   → resolved config array
     sps_theme_inject($con)   → <style> + <script> that applies the theme
   =============================================================================*/

if (!function_exists('sps_theme_inject')) {

/* ── color helpers ─────────────────────────────────────────────────────── */
function sps_hex_rgb($hex) {
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    return ctype_xdigit($hex) ? [hexdec(substr($hex,0,2)), hexdec(substr($hex,2,2)), hexdec(substr($hex,4,2))] : [111,217,67];
}

function sps_hex_mix($hex, $target, $w) { /* w=0 → pure $hex, w=1 → pure $target */
    $a = sps_hex_rgb($hex); $b = sps_hex_rgb($target);
    $r = round($a[0] + ($b[0] - $a[0]) * $w);
    $g = round($a[1] + ($b[1] - $a[1]) * $w);
    $bl = round($a[2] + ($b[2] - $a[2]) * $w);
    return sprintf('#%02X%02X%02X', $r, $g, $bl);
}

function sps_hex_darken($hex, $amt) { return sps_hex_mix($hex, '#000000', $amt); }
function sps_hex_soft($hex, $amt)   { return sps_hex_mix($hex, '#FFFFFF', $amt); }

function sps_hex_rgb_str($hex) { $r = sps_hex_rgb($hex); return implode(', ', $r); }

function sps_hex_luma($hex) { $r = sps_hex_rgb($hex); return (0.299*$r[0] + 0.587*$r[1] + 0.114*$r[2]) / 255; }

/* ── presets ────────────────────────────────────────────────────────────── */
function sps_theme_presets() {
    return [
        'classic' => [
            'name' => 'Classic', 'tag' => 'The current look — clean, safe, familiar.',
            'mode' => 'light', 'accent' => '#6FD943',
            'bg' => '#f0f4f3', 'surface' => '#ffffff', 'surface2' => '#f7f9f8', 'surface3' => '#eef2f0',
            'border' => '#e6ebe8', 'border2' => '#d7dfdb',
            'text' => '#212529', 'muted' => '#6c757d',
            'sidebar_bg' => '#ffffff', 'sidebar_text' => '#1f2328', 'sidebar_muted' => '#6c757d',
            'header_bg' => 'transparent', 'glass' => false, 'glow' => false,
            'shadow' => '0 2px 6px rgba(16,32,28,.05)',
            'grad' => 'linear-gradient(141.55deg, rgba(240,244,243,0) 3.46%, #f0f4f3 99.86%)',
        ],
        'modern' => [
            'name' => 'Modern Light', 'tag' => 'Soft white, refined borders, gentle lift on cards.',
            'mode' => 'light', 'accent' => '#4361ee',
            'bg' => '#f3f5fb', 'surface' => '#ffffff', 'surface2' => '#f7f8fc', 'surface3' => '#edf0f8',
            'border' => '#e7eaf3', 'border2' => '#d6dbea',
            'text' => '#1a1f33', 'muted' => '#6a7180',
            'sidebar_bg' => '#ffffff', 'sidebar_text' => '#1a1f33', 'sidebar_muted' => '#6a7180',
            'header_bg' => 'rgba(255,255,255,.88)', 'glass' => false, 'glow' => false,
            'shadow' => '0 1px 2px rgba(17,24,60,.05), 0 10px 28px rgba(17,24,60,.07)',
            'grad' => 'radial-gradient(1200px 500px at 100% -10%, rgba(67,97,238,.06), transparent), linear-gradient(160deg,#f5f6fc 0%, #eef1fa 100%)',
        ],
        'sleek' => [
            'name' => 'Sleek', 'tag' => 'User-first: airy, calm, high-contrast — the easiest to work in all day.',
            'mode' => 'light', 'accent' => '#2f7cf6',
            'bg' => '#f4f6fa', 'surface' => '#ffffff', 'surface2' => '#f8fafc', 'surface3' => '#eef2f7',
            'border' => '#e3e8ef', 'border2' => '#cdd6e1',
            'text' => '#172033', 'muted' => '#5c6b82',
            'sidebar_bg' => '#ffffff', 'sidebar_text' => '#27313f', 'sidebar_muted' => '#7c8899',
            'header_bg' => 'rgba(255,255,255,.88)', 'glass' => false, 'glow' => false,
            'shadow' => '0 1px 2px rgba(15,32,60,.05), 0 12px 30px rgba(15,32,60,.06)',
            'grad' => 'linear-gradient(160deg,#f6f8fb 0%, #eef3f9 100%)',
        ],
        'executive' => [
            'name' => 'Executive', 'tag' => 'Realistic: deep navy sidebar, warm paper, gold accent.',
            'mode' => 'light', 'accent' => '#B08D3E',
            'bg' => '#f1eee6', 'surface' => '#fdfcf8', 'surface2' => '#f7f4ec', 'surface3' => '#efe9dc',
            'border' => '#e2dac6', 'border2' => '#d2c7ad',
            'text' => '#26231c', 'muted' => '#75705f',
            'sidebar_bg' => '#14263B', 'sidebar_text' => '#c9d4e2', 'sidebar_muted' => '#8fa0b3',
            'header_bg' => 'rgba(253,252,248,.9)', 'glass' => false, 'glow' => false,
            'shadow' => '0 1px 2px rgba(70,55,20,.10), 0 14px 34px rgba(70,55,20,.10)',
            'grad' => 'linear-gradient(150deg, #f4f1e9 0%, #ece8dc 100%)',
        ],
        'night' => [
            'name' => 'Night', 'tag' => 'Eye-care dark: warm dimmed surfaces, low blue light.',
            'mode' => 'dark', 'accent' => '#D4A553',
            'bg' => '#14120e', 'surface' => '#1b1812', 'surface2' => '#211d15', 'surface3' => '#292418',
            'border' => '#3a3224', 'border2' => '#4c4130',
            'text' => '#dbd5c6', 'muted' => '#9f9480',
            'sidebar_bg' => '#181511', 'sidebar_text' => '#d0c9b9', 'sidebar_muted' => '#96896f',
            'header_bg' => 'rgba(27,24,18,.92)', 'glass' => false, 'glow' => false,
            'shadow' => '0 1px 2px rgba(0,0,0,.35), 0 16px 44px rgba(0,0,0,.45)',
            'grad' => 'linear-gradient(160deg, #181510 0%, #121009 100%)',
        ],
        'midnight' => [
            'name' => 'Midnight', 'tag' => 'Cool blue-dark with a subtle cyan glow.',
            'mode' => 'dark', 'accent' => '#38bdf8',
            'bg' => '#0a0e18', 'surface' => '#111827', 'surface2' => '#16202f', 'surface3' => '#1c2938',
            'border' => '#273445', 'border2' => '#33435a',
            'text' => '#e2e8f0', 'muted' => '#8b98ad',
            'sidebar_bg' => '#0d1322', 'sidebar_text' => '#c7d3e6', 'sidebar_muted' => '#7f8ca2',
            'header_bg' => 'rgba(17,24,39,.92)', 'glass' => false, 'glow' => true,
            'shadow' => '0 1px 2px rgba(0,0,0,.4), 0 18px 48px rgba(0,0,0,.5)',
            'grad' => 'radial-gradient(900px 420px at 15% -5%, rgba(56,189,248,.10), transparent), linear-gradient(165deg,#0b1020 0%, #080b14 100%)',
        ],
        'aurora' => [
            'name' => 'Aurora', 'tag' => 'Dark glass: blurred translucent cards over a drifting gradient.',
            'mode' => 'dark', 'accent' => '#8B5CF6',
            'bg' => '#0a0e1a', 'surface' => 'rgba(20,26,48,.60)', 'surface2' => 'rgba(26,34,60,.55)', 'surface3' => 'rgba(32,42,72,.70)',
            'border' => 'rgba(150,170,230,.18)', 'border2' => 'rgba(150,170,230,.30)',
            'text' => '#e3e8f2', 'muted' => '#9aa5bd',
            'sidebar_bg' => 'rgba(10,14,26,.86)', 'sidebar_text' => '#d9e0f2', 'sidebar_muted' => '#8d97ad',
            'header_bg' => 'rgba(13,18,32,.55)', 'glass' => true, 'glow' => true,
            'shadow' => '0 8px 32px rgba(0,0,0,.45)',
            'grad' => 'radial-gradient(1100px 600px at 85% -10%, rgba(139,92,246,.16), transparent 60%), radial-gradient(900px 500px at -10% 110%, rgba(56,189,248,.12), transparent 60%), #0a0e1a',
        ],
        'neon' => [
            'name' => 'Neon', 'tag' => 'Advanced dark: neon glow, gradient focus, animated active nav.',
            'mode' => 'dark', 'accent' => '#00E5FF',
            'bg' => '#08080f', 'surface' => '#12121f', 'surface2' => '#191929', 'surface3' => '#202034',
            'border' => '#2c2c4a', 'border2' => '#3d3d66',
            'text' => '#e9e9f6', 'muted' => '#9c9cba',
            'sidebar_bg' => '#0c0c18', 'sidebar_text' => '#d6d6ef', 'sidebar_muted' => '#82829f',
            'header_bg' => 'rgba(18,18,31,.92)', 'glass' => false, 'glow' => true,
            'shadow' => '0 1px 2px rgba(0,0,0,.5), 0 0 0 1px rgba(0,229,255,.06), 0 20px 60px rgba(0,0,0,.55)',
            'grad' => 'linear-gradient(165deg, #0b0b18 0%, #07070d 100%)',
        ],
        'custom' => [
            'name' => 'Custom', 'tag' => 'Build your own — mix colors, surfaces, and typography.',
            'mode' => 'light', 'accent' => '#4361ee',
            'bg' => '#f9fafb', 'surface' => '#ffffff', 'surface2' => '#f7f8fa', 'surface3' => '#eef0f4',
            'border' => '#e5e7eb', 'border2' => '#d1d5db',
            'text' => '#111827', 'muted' => '#6b7280',
            'sidebar_bg' => '#ffffff', 'sidebar_text' => '#1f2937', 'sidebar_muted' => '#6b7280',
            'header_bg' => 'rgba(255,255,255,.88)', 'glass' => false, 'glow' => false,
            'shadow' => '0 1px 3px rgba(0,0,0,.06), 0 6px 24px rgba(0,0,0,.05)',
            'grad' => 'linear-gradient(160deg, #f9fafb 0%, #f3f4f6 100%)',
        ],
    ];
}

function sps_theme_preset_ids() { return array_keys(sps_theme_presets()); }

function sps_theme_accent_names() {
    return [
        '1' => 'Pro Green', '2' => 'Royal Violet', '3' => 'Fresh Lime', '4' => 'Deep Ocean',
        '5' => 'Crimson Rose', '6' => 'Sky Blue', '7' => 'Magenta', '8' => 'Golden', '9' => 'Graphite',
    ];
}

function sps_theme_accent_hex($n) {
    return [
        '1' => '#0CAF60', '2' => '#584ED2', '3' => '#6FD943', '4' => '#145388', '5' => '#B9406B',
        '6' => '#008ECC', '7' => '#922C88', '8' => '#C0A145', '9' => '#48494B',
    ][(string)$n] ?? '#6FD943';
}

/* ── config ─────────────────────────────────────────────────────────────── */
function sps_theme_config($con) {
    $def = [
        'preset' => 'classic', 'accent' => '', 'accent_custom' => '',
        'bg' => 'light', 'radius' => 'default', 'compact' => '0', 'font' => 'normal',
        'effects' => '1', 'cursor' => 'ring', 'motion' => 'on',
        'sidebar' => 'auto', 'header' => 'auto', 'glow' => '0',
         'card_bg' => '', 'sidebar_bg_c' => '', 'header_bg_c' => '', 'header_text' => '',
         'heading_color' => '', 'link_color' => '', 'link_hover' => '',
         'muted_color' => '', 'code_color' => '',
         'text_color' => '', 'btn_bg' => '', 'btn_text' => '', 'btn_style' => '',
         'bg_color' => '', 'bg_cover' => '', 'bg_cover_dim' => '35',
        'font_family' => 'system', 'font_size' => 'normal',
        'heading_weight' => '600', 'line_height' => 'normal', 'letter_spacing' => 'normal',
    ];
    /* single read of every theme key */
    $q = @mysqli_query($con, "SELECT marker_key, marker_value FROM settings_marker WHERE marker_key LIKE 'crm_theme\\_%'");
    if ($q) while ($r = mysqli_fetch_assoc($q)) {
        $k = substr($r['marker_key'], 10);
        if (array_key_exists($k, $def)) $def[$k] = trim($r['marker_value']);
    }
    /* validate */
    $def['preset'] = in_array($def['preset'], sps_theme_preset_ids(), true) ? $def['preset'] : 'classic';
    /* empty accent => inherit the preset's own accent */
    $def['accent'] = ($def['accent'] === '' || (ctype_digit((string)$def['accent']) && (int)$def['accent'] >= 1 && (int)$def['accent'] <= 9)) ? (string)$def['accent'] : '';
    if (!preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $def['accent_custom'])) $def['accent_custom'] = '';
    if (!in_array($def['bg'], ['light', 'dark'], true)) $def['bg'] = 'light';
    if (!in_array($def['radius'], ['default', 'square', 'rounded'], true)) $def['radius'] = 'default';
    $def['compact'] = ($def['compact'] === '1') ? '1' : '0';
    if (!in_array($def['font'], ['normal', 'large'], true)) $def['font'] = 'normal';
    $def['effects'] = ($def['effects'] === '1') ? '1' : '0';
    if (!in_array($def['cursor'], ['off', 'dot', 'ring', 'aura', 'cross', 'comet', 'pulse'], true)) $def['cursor'] = 'ring';
    $def['motion'] = ($def['motion'] === 'on') ? 'on' : 'off';
    if (!in_array($def['sidebar'], ['auto', 'light', 'dark'], true)) $def['sidebar'] = 'auto';
    if (!in_array($def['header'], ['auto', 'light', 'dark', 'transparent'], true)) $def['header'] = 'auto';
    $def['glow'] = ($def['glow'] === '1') ? '1' : '0';
    /* shell/card colour overrides: empty = follow the preset (auto) */
    $hexValid = fn($v) => preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $v);
    foreach (['card_bg', 'sidebar_bg_c', 'header_bg_c'] as $ck) {
        $def[$ck] = ($def[$ck] !== '' && $hexValid($def[$ck])) ? $def[$ck] : '';
    }
     foreach (['heading_color','link_color','link_hover','muted_color','code_color'] as $ck) {
         $def[$ck] = ($def[$ck] !== '' && $hexValid($def[$ck])) ? $def[$ck] : '';
     }
     /* header text color override */
     if (!preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $def['header_text'])) $def['header_text'] = '';
     /* global text + button colour/style overrides */
     foreach (['text_color','btn_bg','btn_text'] as $ck) {
         $def[$ck] = ($def[$ck] !== '' && $hexValid($def[$ck])) ? $def[$ck] : '';
     }
     if (!in_array($def['btn_style'], ['', 'filled', 'soft', 'outline', 'gradient'], true)) $def['btn_style'] = '';
     /* body background: colour or uploaded image */
     $def['bg_color'] = ($def['bg_color'] !== '' && $hexValid($def['bg_color'])) ? $def['bg_color'] : '';
     $def['bg_cover_dim'] = (string)max(0, min(85, (int)$def['bg_cover_dim']));
     if (preg_match('/^[A-Za-z0-9._-]+$/', $def['bg_cover'])) {
         if (!file_exists(__DIR__ . '/../uploads/theme_bg/' . $def['bg_cover'])) $def['bg_cover'] = '';
     } else { $def['bg_cover'] = ''; }
     /* typography */
    if (!in_array($def['font_family'], ['system','open-sans','inter','poppins','roboto','nunito'], true)) $def['font_family'] = 'system';
    if (!in_array($def['font_size'], ['xs','sm','normal','lg','xl'], true)) $def['font_size'] = 'normal';
    if (!in_array($def['heading_weight'], ['300','400','500','600','700'], true)) $def['heading_weight'] = '600';
    if (!in_array($def['line_height'], ['tight','normal','relaxed'], true)) $def['line_height'] = 'normal';
    if (!in_array($def['letter_spacing'], ['tight','normal','wide'], true)) $def['letter_spacing'] = 'normal';
    return $def;
}

/* ── palette resolution (tokens consumed by the CSS) ───────────────────── */
function sps_palette($cfg) {
    $P = sps_theme_presets();
    $p = $P[$cfg['preset']] ?? $P['classic'];

    /* accent resolution: custom hex > native swatch > preset default */
    $acc = '';
    if ($cfg['accent_custom'] !== '') $acc = $cfg['accent_custom'];
    elseif ($cfg['accent'] !== '' && $cfg['accent'] !== null) $acc = sps_theme_accent_hex($cfg['accent']);
    $acc = $acc !== '' ? $acc : $p['accent'];
    $acc = preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $acc) ? $acc : '#6FD943';

    /* custom designer mode derives its shell from the light/dark choice */
    if ($cfg['preset'] === 'custom') {
        $base = $cfg['bg'] === 'dark' ? $P['midnight'] : $P['classic'];
        $p = $base;
        $p['accent'] = $acc;
    } else {
        /* user can still override the preset's shell */
        if ($cfg['sidebar'] === 'light')  { $p['sidebar_bg'] = '#ffffff'; $p['sidebar_text'] = '#1a1f33'; $p['sidebar_muted'] = '#6a7180'; }
        if ($cfg['sidebar'] === 'dark')   { $p['sidebar_bg'] = '#0e1524'; $p['sidebar_text'] = '#c7d3e6'; $p['sidebar_muted'] = '#7f8ca2'; }
        if ($cfg['header'] === 'light')       $p['header_bg'] = '#ffffff';
        elseif ($cfg['header'] === 'dark')    $p['header_bg'] = $cfg['bg'] === 'dark' ? $p['surface'] : '#111827';
        elseif ($cfg['header'] === 'transparent') $p['header_bg'] = 'transparent';
    }

    /* shell colour overrides */
    if ($cfg['sidebar_bg_c'] !== '') $p['sidebar_bg'] = $cfg['sidebar_bg_c'];
    if ($cfg['header_bg_c'] !== '')  $p['header_bg']  = $cfg['header_bg_c'];

    $mode = ($cfg['preset'] === 'custom') ? ($cfg['bg'] === 'dark' ? 'dark' : 'light') : $p['mode'];
    $accentRgb = sps_hex_rgb_str($acc);

    /* card bg: custom hex > preset surface */
    $cardBg = $cfg['card_bg'] !== '' ? $cfg['card_bg'] : $p['surface'];

    /* global text override */
    $bodyText = $cfg['text_color'] !== '' ? $cfg['text_color'] : $p['text'];

    /* button palette: btn_bg > accent; btn_text auto-contrast unless overridden */
    $btnBg = ($cfg['btn_bg'] !== '' && $cfg['btn_bg'] !== null) ? $cfg['btn_bg'] : $acc;
    $btnBgLuma = sps_hex_luma($btnBg);
    $btnText = $cfg['btn_text'] !== '' ? $cfg['btn_text'] : ($btnBgLuma > 0.55 ? '#10141f' : '#ffffff');
    $btnHover = sps_hex_darken($btnBg, .10);

    return [
        'preset'   => $cfg['preset'],
        'mode'     => $mode,
        'accent'   => $acc, 'accent_rgb' => $accentRgb,
        'accent_hover' => sps_hex_darken($acc, .10),
        'accent_border' => sps_hex_darken($acc, .14),
        'accent_active' => sps_hex_darken($acc, .18),
        'soft'     => sps_hex_soft($acc, .82),
        'soft2'    => sps_hex_soft($acc, .68),
        'alert_ink'=> sps_hex_darken($acc, .42),
        'text'     => $bodyText, 'muted' => $p['muted'], 'muted2' => sps_hex_mix($p['muted'], $p['text'], .35),
        /* text color overrides */
        'heading_color' => $cfg['heading_color'] ?: $p['text'],
        'link_color' => $cfg['link_color'] ?: $acc,
        'link_hover' => $cfg['link_hover'] ?: ($cfg['link_color'] !== '' ? sps_hex_darken($cfg['link_color'], .12) : sps_hex_darken($acc, .12)),
        'muted_color' => $cfg['muted_color'] ?: $p['muted'],
        'code_color'  => $cfg['code_color'] ?: '#e83e8c',
        /* button tokens */
        'btn_bg'   => $btnBg, 'btn_text' => $btnText, 'btn_hover' => $btnHover, 'btn_style' => $cfg['btn_style'] !== '' ? $cfg['btn_style'] : 'filled',
        'bg'       => $p['bg'], 'surface' => $p['surface'], 'surface2' => $p['surface2'], 'surface3' => $p['surface3'],
        'card'     => $cardBg,
        'border'   => $p['border'], 'border2' => $p['border2'],
        'sidebar_bg' => $p['sidebar_bg'], 'sidebar_text' => $p['sidebar_text'], 'sidebar_muted' => $p['sidebar_muted'],
         'header_bg'  => $p['header_bg'],
         'header_text' => ($cfg['header_text'] !== '') ? $cfg['header_text'] : $p['text'],
         'bg_color'   => $cfg['bg_color'],
         'bg_cover'   => $cfg['bg_cover'],
         'bg_cover_dim' => (int)$cfg['bg_cover_dim'],
         'glass'    => (bool)$p['glass'],
        'glow'     => ($p['glow'] || $cfg['glow'] === '1'),
        'shadow'   => $p['shadow'],
        'grad'     => $p['grad'],
    ];
}

/* ── CSS ────────────────────────────────────────────────────────────────── */
function sps_theme_css($cfg) {
    $t = sps_palette($cfg);
    $dark   = $t['mode'] === 'dark';
    $glass  = $cfg['preset'] === 'aurora' || ($cfg['preset'] === 'custom' && false);
    $neon   = $cfg['preset'] === 'neon';
    $glow   = $t['glow'];
    $c = '';

    /* ── token root ─────────────────────────────────────────────────── */
    $c .= "body.sps-root{"
        . "--sps-accent:{$t['accent']};--sps-accent-hover:{$t['accent_hover']};--sps-accent-border:{$t['accent_border']};"
        . "--sps-accent-active:{$t['accent_active']};--sps-accent-rgb:{$t['accent_rgb']};"
        . "--sps-soft:{$t['soft']};--sps-soft2:{$t['soft2']};--sps-alert-ink:{$t['alert_ink']};"
        . "--sps-bg:{$t['bg']};--sps-surface:{$t['surface']};--sps-surface2:{$t['surface2']};--sps-surface3:{$t['surface3']};"
        . "--sps-card:{$t['card']};"
        . "--sps-border:{$t['border']};--sps-border2:{$t['border2']};--sps-text:{$t['text']};--sps-muted:{$t['muted']};"
        . "--sps-heading:{$t['heading_color']};--sps-link:{$t['link_color']};--sps-link-hover:{$t['link_hover']};"
        . "--sps-muted-text:{$t['muted_color']};--sps-code:{$t['code_color']};"
        . "--sps-sidebar-bg:{$t['sidebar_bg']};--sps-sidebar-text:{$t['sidebar_text']};--sps-sidebar-muted:{$t['sidebar_muted']};"
        . "--sps-header-bg:{$t['header_bg']};--sps-header-text:{$t['header_text']};--sps-shadow:{$t['shadow']};"
        . "--sps-btn-bg:{$t['btn_bg']};--sps-btn-text:{$t['btn_text']};--sps-btn-hover:{$t['btn_hover']};--sps-btn-border:{$t['btn_bg']};--sps-btn-style:{$t['btn_style']};}\n";

    /* ── workspace background ────────────────────────────────────────── */
    $c .= "body.sps-root{background:{$t['grad']} !important;background-attachment:fixed;color:var(--sps-text)}\n"
        . "body.sps-root .dash-container,body.sps-root .dash-content{background:transparent}\n";

    /* ── header shell (solid "command bar"; background/text controlled by
       --sps-header-bg / --sps-header-text — the surface the new header.php
       stylesheet styles. 'transparent' header mode maps to a glass bar.) ── */
    $c .= "body.sps-root .dash-header{background:var(--sps-header-bg,var(--sps-surface));box-shadow:var(--sps-shadow);border-bottom:1px solid var(--sps-border)}\n"
        . "body.sps-root .dash-header .header-wrapper{background:transparent}\n"
        . "body.sps-root .dash-header .dash-head-link,body.sps-root .dash-header .head-nav-text{color:var(--sps-header-text)}\n";

    /* ── sidebar ─────────────────────────────────────────────────────── */
    $c .= "body.sps-root .dash-sidebar{background:var(--sps-sidebar-bg) !important;border-right-color:var(--sps-border)}\n"
        . "body.sps-root .dash-sidebar .dash-link,body.sps-root .dash-sidebar .dash-mtext,body.sps-root .dash-sidebar .dash-submenu .dash-link{color:var(--sps-sidebar-text)}\n"
        . "body.sps-root .dash-sidebar .dash-caption{color:var(--sps-sidebar-muted)}\n"
        . "body.sps-root .dash-sidebar .dash-item .dash-submenu .dash-item.active>.dash-link,"
        . "body.sps-root .dash-sidebar .dash-item .dash-submenu .dash-item:hover>.dash-link{color:var(--sps-accent)}\n"
        . "body.sps-root .dash-sidebar .dash-item .dash-submenu .dash-item::before{border-right-color:var(--sps-accent)}\n"
        . "body.sps-root .dash-sidebar .dash-navbar>.dash-item.active>.dash-link,"
        . "body.sps-root .dash-sidebar .dash-navbar>.dash-item:hover>.dash-link,"
        . "body.sps-root .dash-sidebar.light-sidebar .dash-navbar>.dash-item.active>.dash-link,"
        . "body.sps-root .dash-sidebar.light-sidebar .dash-navbar>.dash-item:hover>.dash-link{"
        . "background:linear-gradient(141.55deg,var(--sps-accent) 3.46%,var(--sps-accent-hover) 99.86%),var(--sps-accent);color:#fff;"
        . "box-shadow:0 5px 7px -1px rgba(var(--sps-accent-rgb),.30)}\n"
        . "body.sps-root .dash-sidebar .dash-navbar>.dash-item.active>.dash-link i,"
        . "body.sps-root .dash-sidebar .dash-navbar>.dash-item:hover>.dash-link i{color:var(--sps-sidebar-bg)}\n";

    /* ── surfaces & type ─────────────────────────────────────────────── */
    $c .= "body.sps-root .card,body.sps-root .aut-card{background:var(--sps-card);border-color:var(--sps-border);color:var(--sps-text);box-shadow:var(--sps-shadow)}\n"
        . "body.sps-root .card-header,body.sps-root .card-h{background:var(--sps-surface2);border-color:var(--sps-border);color:var(--sps-text)}\n"
        . "body.sps-root .card-footer{background:var(--sps-surface2);border-color:var(--sps-border)}\n"
        . "body.sps-root h1,body.sps-root h2,body.sps-root h3,body.sps-root h4,body.sps-root h5,body.sps-root h6,body.sps-root .page-header-title h4{color:var(--sps-heading)}\n"
        . "body.sps-root .text-muted,body.sps-root .text-secondary,body.sps-root .placeholder-hint{color:var(--sps-muted-text) !important}\n"
        . "body.sps-root hr{border-color:var(--sps-border)}\n"
        . "body.sps-root .breadcrumb-item{color:var(--sps-muted-text)}\n"
        . "body.sps-root .breadcrumb-item a,body.sps-root .breadcrumb-item.active{color:var(--sps-text)}\n"
        . "body.sps-root a:not(.btn):not(.nav-link):not(.dash-link){color:var(--sps-link)}\n"
        . "body.sps-root a:not(.btn):not(.nav-link):not(.dash-link):hover{color:var(--sps-link-hover)}\n"
        . "body.sps-root code,body.sps-root .code,body.sps-root .text-code{color:var(--sps-code)}\n";

    /* ── the accent consumer map (replaces native theme-N blocks) ────── */
    $c .= "body.sps-root .bg-primary{background:linear-gradient(141.55deg,var(--sps-accent) 3.46%,var(--sps-accent-hover) 99.86%),var(--sps-accent) !important}\n"
        . "body.sps-root .text-primary{color:var(--sps-accent) !important}\n"
        . "body.sps-root .dash-header .drp-language .drp-text,body.sps-root .dash-header .dash-head-link>i:not(.nocolor){color:var(--sps-accent)}\n"
        . "body.sps-root .dash-header .drp-company .theme-avtar{background:var(--sps-soft);color:var(--sps-accent)}\n"
        . "body.sps-root .list-group-item.active{background:linear-gradient(141.55deg,var(--sps-accent) 3.46%,var(--sps-accent-hover) 99.86%),var(--sps-accent) !important;border-color:var(--sps-accent)}\n"
        . "body.sps-root .list-group-item{background:var(--sps-surface);border-color:var(--sps-border);color:var(--sps-text)}\n"
        . "body.sps-root .progress-bar:not([class*=bg-]){background:var(--sps-accent)}\n"
        . "body.sps-root .progress{background:var(--sps-surface3)}\n"
        . "body.sps-root .btn-primary{--bs-btn-color:var(--sps-btn-text);--bs-btn-bg:var(--sps-btn-bg);--bs-btn-border-color:var(--sps-btn-border);"
        . "--bs-btn-hover-color:var(--sps-btn-text);--bs-btn-hover-bg:var(--sps-btn-hover);--bs-btn-hover-border-color:var(--sps-btn-hover);"
        . "--bs-btn-focus-shadow-rgb:var(--sps-accent-rgb);--bs-btn-active-color:var(--sps-btn-text);--bs-btn-active-bg:var(--sps-btn-hover);"
        . "--bs-btn-active-border-color:var(--sps-btn-hover);--bs-btn-disabled-color:var(--sps-btn-text);--bs-btn-disabled-bg:var(--sps-btn-bg);--bs-btn-disabled-border-color:var(--sps-btn-border)}\n"
        . "body.sps-root .btn-outline-primary{--bs-btn-color:var(--sps-accent);--bs-btn-border-color:var(--sps-accent);"
        . "--bs-btn-hover-color:var(--sps-btn-text);--bs-btn-hover-bg:var(--sps-btn-bg);--bs-btn-hover-border-color:var(--sps-btn-border);"
        . "--bs-btn-focus-shadow-rgb:var(--sps-accent-rgb);--bs-btn-active-color:var(--sps-btn-text);--bs-btn-active-bg:var(--sps-btn-bg);"
        . "--bs-btn-active-border-color:var(--sps-btn-border)}\n"
        . "body.sps-root .btn-light-primary{background:var(--sps-soft);color:var(--sps-accent);border-color:var(--sps-soft)}\n"
        . "body.sps-root .btn-light-primary:hover,body.sps-root .btn-light-primary:focus,"
        . "body.sps-root .btn-light-primary:active,body.sps-root .btn-check:checked+.btn-light-primary{background:var(--sps-accent);color:var(--sps-btn-text);border-color:var(--sps-accent)}\n"
        /* button style variants (body class set by boot) */
        . "body.sps-root input[type=submit]:not(.btn),body.sps-root button[type=submit]:not(.btn),body.sps-root .btn{--sps-btn-this-bg:var(--sps-btn-bg);--sps-btn-this-text:var(--sps-btn-text)}\n"
        . "body.sps-root input[type=submit]:not(.btn),body.sps-root button[type=submit]:not(.btn){border-radius:10px}\n"
        . "body.sps-btn-soft input[type=submit]:not(.btn),body.sps-btn-soft button[type=submit]:not(.btn){--sps-btn-this-bg:var(--sps-soft);--sps-btn-this-text:var(--sps-accent)}\n"
        . "body.sps-btn-outline input[type=submit]:not(.btn),body.sps-btn-outline button[type=submit]:not(.btn){--sps-btn-this-bg:transparent;--sps-btn-this-text:var(--sps-accent)}\n"
        . "body.sps-btn-gradient input[type=submit]:not(.btn),body.sps-btn-gradient button[type=submit]:not(.btn){--sps-btn-this-bg:linear-gradient(135deg,var(--sps-btn-bg),var(--sps-btn-hover));--sps-btn-this-text:var(--sps-btn-text)}\n"
        . "body.sps-root input[type=submit]:not(.btn),body.sps-root button[type=submit]:not(.btn){background:var(--sps-btn-this-bg);border-color:var(--sps-btn-this-bg);color:var(--sps-btn-this-text)}\n"
        . "body.sps-root input[type=submit]:not(.btn):hover,body.sps-root button[type=submit]:not(.btn):hover{background:var(--sps-btn-hover);border-color:var(--sps-btn-hover)}\n"
        . "body.sps-root .btn-primary{background:var(--sps-btn-style-bg,var(--bs-btn-bg));color:var(--sps-btn-style-text,var(--bs-btn-color))}\n"
        . "body.sps-btn-soft .btn-primary{--sps-btn-style-bg:var(--sps-soft);--sps-btn-style-text:var(--sps-accent);box-shadow:none}\n"
        . "body.sps-btn-soft .btn-primary:hover{--sps-btn-style-bg:var(--sps-accent);--sps-btn-style-text:var(--sps-btn-text)}\n"
        . "body.sps-btn-outline .btn-primary{--sps-btn-style-bg:transparent;--sps-btn-style-text:var(--sps-accent);box-shadow:none}\n"
        . "body.sps-btn-outline .btn-primary:hover{--sps-btn-style-bg:var(--sps-accent);--sps-btn-style-text:var(--sps-btn-text)}\n"
        . "body.sps-btn-gradient .btn-primary{--sps-btn-style-bg:linear-gradient(135deg,var(--sps-btn-bg),var(--sps-btn-hover))}\n"
        . "body.sps-root .alert-primary{--bs-alert-color:var(--sps-alert-ink);--bs-alert-bg:var(--sps-soft);--bs-alert-border-color:var(--sps-soft2)}\n"
        . "body.sps-root .alert-primary .alert-link{color:var(--sps-accent-active)}\n"
        . "body.sps-root .badge.bg-light-primary{background:var(--sps-soft);color:var(--sps-accent);border-color:var(--sps-soft)}\n"
        . "body.sps-root .page-item.active .page-link{background:var(--sps-accent);border-color:var(--sps-accent)}\n"
        . "body.sps-root .page-link{background:var(--sps-surface);border-color:var(--sps-border);color:var(--sps-text)}\n"
        . "body.sps-root .page-item.disabled .page-link{background:var(--sps-surface2);color:var(--sps-muted-text)}\n"
        . "body.sps-root .nav-pills .nav-link.active,body.sps-root .nav-pills .show>.nav-link{color:#fff;background:linear-gradient(141.55deg,var(--sps-accent) 3.46%,var(--sps-accent-hover) 99.86%),var(--sps-accent)}\n"
        . "body.sps-root .nav-link{color:var(--sps-muted-text)}"
        . "body.sps-root .nav-link:hover,body.sps-root .nav-link:focus{color:var(--sps-accent)}\n"
        . "body.sps-root .nav-tabs .nav-link.active,body.sps-root .nav-tabs .nav-item.show .nav-link{color:var(--sps-accent);border-color:var(--sps-border) var(--sps-border) transparent}\n"
        . "body.sps-root .nav-tabs{border-color:var(--sps-border)}\n"
        . "body.sps-root .form-control,body.sps-root .form-select,body.sps-root .custom-select,"
        . "body.sps-root .dataTable-selector,body.sps-root .dataTable-input{background:var(--sps-surface);border-color:var(--sps-border);color:var(--sps-text)}\n"
        . "body.sps-root .form-control:focus,body.sps-root .form-select:focus,body.sps-root .custom-select:focus,"
        . "body.sps-root .dataTable-selector:focus,body.sps-root .dataTable-input:focus{border-color:var(--sps-accent);box-shadow:0 0 0 .2rem rgba(var(--sps-accent-rgb),.25)}\n"
        . "body.sps-root .form-select,input,select,textarea{color:var(--sps-text)}\n"
        . "body.sps-root .form-check-input:checked,body.sps-root .form-check-input:indeterminate,"
        . "body.sps-root .form-check .form-check-input.input-primary:checked{background-color:var(--sps-accent);border-color:var(--sps-accent)}\n"
        . "body.sps-root .form-check-input:focus{border-color:var(--sps-accent);box-shadow:0 0 0 .2rem rgba(var(--sps-accent-rgb),.18)}\n"
        . "body.sps-root .form-range::-webkit-slider-thumb,body.sps-root .form-range::-moz-range-thumb{background:var(--sps-accent)}\n"
        . "body.sps-root .input-group-text{background:var(--sps-surface2);border-color:var(--sps-border);color:var(--sps-muted-text)}\n"
        . "body.sps-root pre[class*=language-]>code{border-left-color:var(--sps-accent);background-image:linear-gradient(transparent 50%,rgba(var(--sps-accent-rgb),.04) 50%)}\n"
        . "body.sps-root .card .card-header h5:after,body.sps-root .card .card-header .h5:after{background:var(--sps-accent)}\n"
        . "body.sps-root ::selection{background:var(--sps-accent);color:#fff}\n"
        . "body.sps-root .cat-list-btn .btn-primary{--bs-btn-bg:var(--sps-accent);--bs-btn-border-color:var(--sps-accent);--bs-btn-hover-bg:var(--sps-accent-hover);--bs-btn-hover-border-color:var(--sps-accent-border)}\n"
        /* header quick-access pills (page-inline accent uses) */
        . "body.sps-root .dash-header .badge-accent,body.sps-root .header-quick .btn{--sps-hq:var(--sps-accent)}\n"
        . "body.sps-root .table{color:var(--sps-text)}"
        . "body.sps-root .table thead th{color:var(--sps-muted-text);border-color:var(--sps-border)}"
        . "body.sps-root .table td,body.sps-root .table th{border-color:var(--sps-border)}\n";

    /* ── glow layer ──────────────────────────────────────────────────── */
    if ($glow) {
        $c .= "body.sps-root.sps-glow .btn-primary{box-shadow:0 6px 20px rgba(var(--sps-accent-rgb),.32),0 0 0 1px rgba(var(--sps-accent-rgb),.06)}\n"
            . "body.sps-root.sps-glow .card,body.sps-root.sps-glow .aut-card{box-shadow:var(--sps-shadow),0 0 0 1px rgba(var(--sps-accent-rgb),.05)}\n"
            . "body.sps-root.sps-glow .dash-sidebar .dash-navbar>.dash-item.active>.dash-link{box-shadow:0 5px 7px -1px rgba(var(--sps-accent-rgb),.4),0 0 18px rgba(var(--sps-accent-rgb),.35)}\n"
            . "body.sps-root.sps-glow .text-primary{text-shadow:0 0 18px rgba(var(--sps-accent-rgb),.35)}\n";
    }

    /* ── neon specials ───────────────────────────────────────────────── */
    if ($neon) {
        $c .= "body.sps-root.sps-p-neon .btn-primary::after{content:'';position:absolute;inset:0;border-radius:inherit;"
            . "background:linear-gradient(120deg,transparent 30%,rgba(255,255,255,.25) 50%,transparent 70%);"
            . "transform:translateX(-120%);animation:spsNeonSweep 3.2s ease-in-out infinite;pointer-events:none}\n"
            . "@keyframes spsNeonSweep{0%,60%{transform:translateX(-120%)}100%{transform:translateX(120%)}}\n"
            . "body.sps-root.sps-p-neon .btn-primary{position:relative;overflow:hidden}\n"
            . "body.sps-root.sps-p-neon .dash-sidebar .dash-navbar>.dash-item.active>.dash-link{"
            . "background:linear-gradient(90deg,var(--sps-accent),#8B5CF6,var(--sps-accent)) !important;background-size:200% !important;animation:spsNeonNav 4s linear infinite}\n"
            . "@keyframes spsNeonNav{0%{background-position:0% 50%}100%{background-position:200% 50%}}\n"
            . "body.sps-root.sps-p-neon ::selection{background:var(--sps-accent);color:#000}\n"
            . "body.sps-root.sps-p-neon code{background:rgba(var(--sps-accent-rgb),.12);color:var(--sps-accent)}\n";
    }

    /* ── dark sweep (all dark themes share the same surface rules) ──── */
    if ($dark) {
        $c .= "body.sps-bg-dark{background:var(--sps-bg) !important;color:var(--sps-text)}\n"
            . "body.sps-bg-dark .dash-header{background:var(--sps-header-bg,var(--sps-surface)) !important;color:var(--sps-text)}\n"
            . "body.sps-bg-dark .dash-header .header-wrapper{background:transparent !important}\n"
            . "body.sps-bg-dark .dash-sidebar{background:var(--sps-sidebar-bg) !important}\n"
            . "body.sps-bg-dark .card,body.sps-bg-dark .aut-card{background:var(--sps-card);border-color:var(--sps-border);color:var(--sps-text);box-shadow:var(--sps-shadow)}\n"
            . "body.sps-bg-dark .card-header,body.sps-bg-dark .card-h{background:var(--sps-surface2);border-color:var(--sps-border);color:var(--sps-text)}\n"
            . "body.sps-bg-dark .card-footer,body.sps-bg-dark .step-head{background:var(--sps-surface2);border-color:var(--sps-border)}\n"
            . "body.sps-bg-dark .step-body{background:var(--sps-surface3)}\n"
            . "body.sps-bg-dark .form-control,body.sps-bg-dark .form-select,body.sps-bg-dark .custom-select,"
            . "body.sps-bg-dark .dataTable-selector,body.sps-bg-dark .dataTable-input{background:var(--sps-surface3);border-color:var(--sps-border);color:var(--sps-text)}\n"
            . "body.sps-bg-dark .form-control:focus,body.sps-bg-dark .form-select:focus{border-color:var(--sps-accent);box-shadow:0 0 0 .2rem rgba(var(--sps-accent-rgb),.25);background:var(--sps-surface3)}\n"
            . "body.sps-bg-dark .dropdown-menu{background:var(--sps-surface);border-color:var(--sps-border);color:var(--sps-text)}\n"
            . "body.sps-bg-dark .dropdown-item{color:var(--sps-text)}\n"
            . "body.sps-bg-dark .dropdown-item:hover,.sps-bg-dark .dropdown-item:focus{background:var(--sps-surface3);color:var(--sps-text)}\n"
            . "body.sps-bg-dark .dropdown-divider{border-color:var(--sps-border)}\n"
            . "body.sps-bg-dark .modal-content{background:var(--sps-surface);border-color:var(--sps-border);color:var(--sps-text)}\n"
            . "body.sps-bg-dark .modal-header,body.sps-bg-dark .modal-footer{border-color:var(--sps-border)}\n"
            . "body.sps-bg-dark .modal-backdrop{background:#000}\n"
            . "body.sps-bg-dark .btn-close{filter:invert(1) grayscale(1)}\n"
            . "body.sps-bg-dark .table{color:var(--sps-text)}\n"
            . "body.sps-bg-dark .table thead th{color:var(--sps-muted-text);border-color:var(--sps-border)}\n"
            . "body.sps-bg-dark .table-striped>tbody>tr:nth-of-type(odd){background:var(--sps-surface2)}\n"
            . "body.sps-bg-dark .table-hover>tbody>tr:hover{background:var(--sps-surface3)}\n"
            . "body.sps-bg-dark .table-bordered,body.sps-bg-dark .table-bordered td,body.sps-bg-dark .table-bordered th{border-color:var(--sps-border)}\n"
            . "body.sps-bg-dark .bg-white{background:var(--sps-surface) !important}\n"
            . "body.sps-bg-dark .bg-light{background:var(--sps-surface2) !important}\n"
            . "body.sps-bg-dark .bg-body{background:var(--sps-bg) !important}\n"
            /* DataTables */
            . "body.sps-bg-dark .dataTable-wrapper .dataTable-top,body.sps-bg-dark .dataTable-wrapper .dataTable-bottom,"
            . "body.sps-bg-dark .dataTables_wrapper .dataTables_filter input,body.sps-bg-dark .dataTables_wrapper .dataTables_length select{background:var(--sps-surface2);border-color:var(--sps-border);color:var(--sps-text)}\n"
            . "body.sps-bg-dark .dataTable-wrapper .dataTable-top,body.sps-bg-dark .dataTable-wrapper .dataTable-bottom{border-color:var(--sps-border)}\n"
            . "body.sps-bg-dark .dt-button,body.sps-bg-dark .buttons-copy,body.sps-bg-dark .buttons-pdf,body.sps-bg-dark .buttons-print,body.sps-bg-dark .buttons-excel{background:var(--sps-surface3)!important;color:var(--sps-text)!important;border-color:var(--sps-border)!important}\n"
            /* simple-datatables pagination */
            . "body.sps-bg-dark .dataTable-pagination li a{background:var(--sps-surface3);color:var(--sps-text)}\n"
            . "body.sps-bg-dark .dataTable-pagination li.active a{background:var(--sps-accent);color:#fff}\n"
            /* pickers & alerts */
            . "body.sps-bg-dark .flatpickr-calendar{background:var(--sps-surface);border-color:var(--sps-border);box-shadow:var(--sps-shadow)}\n"
            . "body.sps-bg-dark .flatpickr-day{color:var(--sps-text)}\n"
            . "body.sps-bg-dark .flatpickr-day:hover{background:var(--sps-surface3)}\n"
            . "body.sps-bg-dark .flatpickr-day.selected,body.sps-bg-dark .flatpickr-day.selected:hover{background:var(--sps-accent);border-color:var(--sps-accent);color:#fff}\n"
            . "body.sps-bg-dark .flatpickr-months .flatpickr-month,body.sps-bg-dark .flatpickr-weekday{color:var(--sps-text)}\n"
            . "body.sps-bg-dark .flatpickr-time input{color:var(--sps-text)}\n"
            . "body.sps-bg-dark .swal2-popup{background:var(--sps-surface) !important;color:var(--sps-text) !important}\n"
            . "body.sps-bg-dark .swal2-title,body.sps-bg-dark .swal2-html-container{color:var(--sps-text) !important}\n"
            /* select2 */
            . "body.sps-bg-dark .select2-container--default .select2-selection--single,"
            . "body.sps-bg-dark .select2-container--default .select2-selection--multiple{background:var(--sps-surface3);border-color:var(--sps-border);color:var(--sps-text)}\n"
            . "body.sps-bg-dark .select2-dropdown{background:var(--sps-surface);border-color:var(--sps-border)}\n"
            . "body.sps-bg-dark .select2-results__option{color:var(--sps-text)}\n"
            . "body.sps-bg-dark .select2-results__option--highlighted{background:var(--sps-accent);color:#fff}\n"
            /* automation + hrm helpers */
            . "body.sps-bg-dark .aut-tbl,body.sps-bg-dark .cond-rows .cond-row,body.sps-bg-dark .field-row{color:var(--sps-text)}\n"
            . "body.sps-bg-dark .aut-card .card-b{color:var(--sps-muted-text)}\n"
            . "body.sps-bg-dark .hrm-panel-body,body.sps-bg-dark .hrm-background{background:var(--sps-surface) !important;border-color:var(--sps-border) !important}\n"
            . "body.sps-bg-dark .list-group-item{background:var(--sps-surface);border-color:var(--sps-border);color:var(--sps-text)}\n"
            . "body.sps-bg-dark .list-group-item:hover{background:var(--sps-surface2)}\n"
            . "body.sps-bg-dark .nav-tabs{border-color:var(--sps-border)}\n"
            . "body.sps-bg-dark .nav-tabs .nav-link{color:var(--sps-muted-text)}\n"
            . "body.sps-bg-dark .nav-tabs .nav-link.active{color:var(--sps-accent)}\n"
            . "body.sps-bg-dark .progress{background:var(--sps-surface3)}\n"
            . "body.sps-bg-dark .input-group-text{background:var(--sps-surface3);border-color:var(--sps-border);color:var(--sps-muted-text)}\n";
    }

    /* ── shell: shared sidebar base (icons, pill active, submenu, toggle) ── */
    $c .= "body.sps-root .dash-sidebar .sidebar-header{background:transparent;border-bottom:1px solid var(--sps-border);box-shadow:none;height:52px;justify-content:center;padding-right:0}\n"
        . "body.sps-root .dash-sidebar #sidebar-toggle{background:var(--sps-surface2);border:1px solid var(--sps-border);color:var(--sps-text);box-shadow:0 2px 6px rgba(0,0,0,.06)}\n"
        . "body.sps-root .dash-sidebar #sidebar-toggle:hover{background:var(--sps-soft);color:var(--sps-accent);border-color:transparent;transform:scale(1.06)}\n"
        . "body.sps-root .dash-sidebar .main-logo{background:transparent !important;min-height:84px;max-height:112px;min-width:236px}\n"
        . "body.sps-root .dash-sidebar .main-logo a img{height:52px !important;max-height:52px !important;width:auto !important;object-fit:contain}\n"
        . "body.sps-root .dash-sidebar .navbar-content{padding:8px 12px}\n"
        . "body.sps-root .dash-sidebar .dash-navbar>.dash-item{margin:4px 0}\n"
        . "body.sps-root .dash-sidebar .dash-link{border-radius:12px;padding:10px 11px;color:var(--sps-sidebar-text);position:relative}\n"
        . "body.sps-root .dash-sidebar .dash-micon{width:34px;height:34px;border-radius:10px;display:inline-flex;align-items:center;justify-content:center;background:var(--sps-surface2);color:var(--sps-sidebar-text);margin-right:12px;flex:0 0 34px;transition:all .22s ease;font-size:17px}\n"
        . "body.sps-root .dash-sidebar .dash-micon i{font-size:17px;line-height:1;color:var(--sps-sidebar-text);transition:color .22s ease}\n"
        . "body.sps-root .dash-sidebar .dash-micon svg{width:18px;height:18px}\n"
        . "body.sps-root .dash-sidebar .dash-navbar>.dash-item.active>.dash-link{background:linear-gradient(141.55deg,var(--sps-accent) 3.46%,var(--sps-accent-hover) 99.86%),var(--sps-accent) !important;color:#fff;box-shadow:0 5px 10px -2px rgba(var(--sps-accent-rgb),.35)}\n"
        . "body.sps-root .dash-sidebar .dash-navbar>.dash-item.active>.dash-link .dash-micon{background:rgba(255,255,255,.22);color:#fff}\n"
        . "body.sps-root .dash-sidebar .dash-navbar>.dash-item.active>.dash-link .dash-micon i,body.sps-root .dash-sidebar .dash-navbar>.dash-item.active>.dash-link .dash-micon svg{color:#fff}\n"
        . "body.sps-root .dash-sidebar .dash-navbar>.dash-item:hover>.dash-link .dash-micon{background:var(--sps-soft);color:var(--sps-accent)}\n"
        . "body.sps-root .dash-sidebar .dash-navbar>.dash-item:hover>.dash-link .dash-micon i{color:var(--sps-accent)}\n"
        . "body.sps-root .dash-sidebar .dash-submenu{margin:4px 0 8px 24px;padding-left:8px;border-left:1px solid var(--sps-border);border-radius:0 10px 10px 0}\n"
        . "body.sps-root .dash-sidebar .dash-submenu .dash-link{border-radius:10px;padding:8px 12px;font-size:13px;color:var(--sps-sidebar-text)}\n"
        . "body.sps-root .dash-sidebar .dash-submenu .dash-item.active>.dash-link{background:var(--sps-soft);color:var(--sps-accent)}\n"
        . "body.sps-root .dash-sidebar .dash-submenu .dash-item.active>.dash-link i{color:var(--sps-accent)}\n"
        . "body.sps-root .dash-sidebar .dash-arrow i{display:none}\n"
        . "body.sps-root .dash-sidebar .dash-arrow::after{content:'\\203A';font-size:16px;color:var(--sps-sidebar-muted);transition:transform .25s ease;display:inline-block;margin-left:auto}\n"
        . "body.sps-root .dash-sidebar .dash-item.active.dash-hasmenu>.dash-link .dash-arrow::after{transform:rotate(90deg)}\n"
        . "body.sps-root.sidebar-collapsed .dash-sidebar .dash-link{border-radius:10px;margin:0 6px}\n"
        . "body.sps-root.sidebar-collapsed .dash-sidebar .dash-micon{margin-right:0;width:38px;height:38px;border-radius:12px}\n"
        . "body.sps-root.sidebar-collapsed .dash-sidebar .navbar-content{padding:8px 6px}\n"
        . "body.sps-root.sidebar-collapsed .dash-sidebar .main-logo{min-width:80px}\n";

    /* ── per-preset layout engine: every theme gets its own shell ─────── */
    $c .= "body.sps-root.sps-p-classic .dash-sidebar{border-right:1px solid var(--sps-border)}\n"
        . "body.sps-root.sps-p-classic .dash-sidebar .dash-navbar>.dash-item.active>.dash-link{border-radius:8px;box-shadow:none}\n"
        // modern: comfortable floating panel with soft lift
        . "body.sps-root.sps-p-modern .dash-sidebar{box-shadow:6px 0 24px -18px rgba(17,24,60,.18)}\n"
        . "body.sps-root.sps-p-modern .dash-sidebar .dash-micon{border-radius:12px}\n"
        // sleek: airy floating card sidebar — friendly, high contrast
        . "body.sps-root.sps-p-sleek .dash-sidebar{padding:14px 10px 14px 22px;border-right:none;background:transparent !important}\n"
        . "body.sps-root.sps-p-sleek .dash-sidebar .navbar-wrapper{background:var(--sps-sidebar-bg);border:1px solid var(--sps-border);border-radius:20px;box-shadow:0 10px 30px -12px rgba(15,32,60,.14);overflow:hidden;height:100%}\n"
        . "body.sps-root.sps-p-sleek .dash-sidebar .dash-link{border-radius:12px;padding:11px 12px}\n"
        . "body.sps-root.sps-p-sleek .dash-sidebar .dash-micon{background:var(--sps-surface);border:1px solid var(--sps-border);border-radius:12px}\n"
        . "body.sps-root.sps-p-sleek .dash-sidebar .dash-navbar>.dash-item.active>.dash-link{background:var(--sps-soft)!important;color:var(--sps-accent)!important;box-shadow:none}\n"
        . "body.sps-root.sps-p-sleek .dash-sidebar .dash-navbar>.dash-item.active>.dash-link .dash-micon{background:var(--sps-accent);border-color:var(--sps-accent)}\n"
        . "body.sps-root.sps-p-sleek .dash-sidebar .dash-navbar>.dash-item.active>.dash-link .dash-micon i{color:#fff}\n"
        // executive: crisp navy sidebar, clean links, accent dot
        . "body.sps-root.sps-p-executive .dash-sidebar{background:var(--sps-sidebar-bg)!important}\n"
        . "body.sps-root.sps-p-executive .dash-sidebar .dash-link{border-radius:6px;letter-spacing:.01em}\n"
        . "body.sps-root.sps-p-executive .dash-sidebar .dash-micon{border-radius:8px;background:rgba(176,141,62,.14);color:#d9c690}\n"
        . "body.sps-root.sps-p-executive .dash-sidebar .dash-navbar>.dash-item.active>.dash-link{background:transparent!important;box-shadow:none;border-left:3px solid var(--sps-accent)}\n"
        . "body.sps-root.sps-p-executive .dash-sidebar .dash-navbar>.dash-item.active>.dash-link .dash-mtext{color:var(--sps-accent)}\n"
        // night: warm dim sidebar with soft amber chips
        . "body.sps-root.sps-p-night .dash-sidebar{background:var(--sps-sidebar-bg)!important}\n"
        . "body.sps-root.sps-p-night .dash-sidebar .dash-micon{background:rgba(255,195,90,.10);color:#e8c07c}\n"
        . "body.sps-root.sps-p-night .dash-sidebar .dash-navbar>.dash-item.active>.dash-link{background:var(--sps-accent)!important;box-shadow:0 6px 16px rgba(212,165,83,.35)}\n"
        // midnight: floating cool panel with cyan glow active
        . "body.sps-root.sps-p-midnight .dash-sidebar{padding:14px 10px 14px 20px;border-right:none;background:transparent!important}\n"
        . "body.sps-root.sps-p-midnight .dash-sidebar .navbar-wrapper{background:var(--sps-sidebar-bg);border:1px solid var(--sps-border);border-radius:18px;box-shadow:0 20px 46px rgba(0,0,0,.5);overflow:hidden;height:100%}\n"
        . "body.sps-root.sps-p-midnight .dash-sidebar .dash-micon{border-radius:12px;background:rgba(56,189,248,.08);color:#9fd7f5}\n"
        . "body.sps-root.sps-p-midnight .dash-sidebar .dash-navbar>.dash-item.active>.dash-link{box-shadow:0 5px 16px rgba(56,189,248,.5)}\n"
        // aurora: full glass floating panel
        . "body.sps-root.sps-p-aurora .dash-sidebar{padding:14px 10px 14px 20px;border-right:none;background:transparent!important}\n"
        . "body.sps-root.sps-p-aurora .dash-sidebar .navbar-wrapper{background:var(--sps-sidebar-bg);border:1px solid rgba(150,170,230,.22);border-radius:20px;backdrop-filter:blur(22px) saturate(1.4);-webkit-backdrop-filter:blur(22px) saturate(1.4);box-shadow:0 24px 60px rgba(0,0,0,.5);overflow:hidden;height:100%}\n"
        . "body.sps-root.sps-p-aurora .dash-sidebar .dash-micon{background:rgba(139,92,246,.14);color:#c9b6ff}\n"
        . "body.sps-root.sps-p-aurora .dash-sidebar .dash-navbar>.dash-item.active>.dash-link{box-shadow:0 5px 18px rgba(139,92,246,.55)}\n"
        // neon: glowing accent rail
        . "body.sps-root.sps-p-neon .dash-sidebar{box-shadow:inset -1px 0 0 rgba(0,229,255,.12)}\n"
        . "body.sps-root.sps-p-neon .dash-sidebar .dash-micon{border:1px solid rgba(0,229,255,.18);border-radius:10px;color:#b6f6ff}\n"
        . "body.sps-root.sps-p-neon .dash-sidebar .dash-navbar>.dash-item.active>.dash-link{background:linear-gradient(90deg,rgba(0,229,255,.16),transparent)!important;color:#eaffff;box-shadow:none;border-left:3px solid var(--sps-accent)}\n"
        . "body.sps-root.sps-p-neon .dash-sidebar .dash-navbar>.dash-item.active>.dash-link .dash-micon{background:var(--sps-accent);border-color:var(--sps-accent)}body.sps-root.sps-p-neon .dash-sidebar .dash-navbar>.dash-item.active>.dash-link .dash-micon i{color:#08080f}\n"
        . "body.sps-bg-dark.sps-p-neon .dash-sidebar{background:var(--sps-sidebar-bg)!important}\n";

    /* ── modern shell: themed header & dropdowns (no white leaks) ────── */
    $c .= "body.sps-root .dash-header{padding:8px 18px;box-shadow:none}\n"
        . "body.sps-root .dash-header .header-wrapper{background:transparent}\n"
        . "body.sps-root .search-wrapper{background:var(--sps-surface);border:1px solid var(--sps-border);box-shadow:var(--sps-shadow);border-radius:26px}\n"
        . "body.sps-root .search-wrapper:hover{box-shadow:0 6px 18px rgba(var(--sps-accent-rgb),.12);border-color:var(--sps-border2)}\n"
        . "body.sps-root .search-input{color:var(--sps-text)}\n"
        . "body.sps-root .search-input::placeholder,body.sps-root .form-control::placeholder,body.sps-root input::placeholder,body.sps-root textarea::placeholder{color:var(--sps-muted-text)}\n"
        . "body.sps-root .search-button{background:linear-gradient(135deg,var(--sps-accent),var(--sps-accent-hover));box-shadow:0 4px 10px rgba(var(--sps-accent-rgb),.28)}\n"
        . "body.sps-root .search-button:hover{transform:scale(1.08);box-shadow:0 6px 14px rgba(var(--sps-accent-rgb),.4)}\n"
        . "body.sps-root .search-button i{color:#fff}\n"
        . "body.sps-root .search-by-selected{background:var(--sps-surface2);border:1px solid var(--sps-border);box-shadow:none;color:var(--sps-text)}\n"
        . "body.sps-root .search-by-selected.active{background:linear-gradient(135deg,var(--sps-accent),var(--sps-accent-hover));border-color:transparent;box-shadow:0 4px 12px rgba(var(--sps-accent-rgb),.3)}\n"
        . "body.sps-root .search-by-selected.active .search-icon,body.sps-root .search-by-selected.active #searchByLabel,body.sps-root .search-by-selected.active .dropdown-arrow{color:#fff}\n"
        . "body.sps-root .search-icon{color:var(--sps-accent)}\n"
        . "body.sps-root #searchByLabel{color:var(--sps-text)}\n"
        . "body.sps-root .dropdown-arrow{color:var(--sps-muted-text)}\n"
        . "body.sps-root .status-filter{border-color:var(--sps-border)}\n"
        . "body.sps-root .search-by-filter{border-color:var(--sps-border)}\n"
        . "body.sps-root .status-option input[type=radio]{border-color:var(--sps-accent)}\n"
        . "body.sps-root .status-option input[type=radio]:checked::after{background:var(--sps-accent)}\n"
        . "body.sps-root .status-option label{color:var(--sps-text)}\n"
        . "body.sps-root .quick-action-btn{background:linear-gradient(135deg,var(--sps-accent),var(--sps-accent-hover));border:none;box-shadow:0 4px 12px rgba(var(--sps-accent-rgb),.3);color:#fff}\n"
        . "body.sps-root .quick-action-btn.active{background:linear-gradient(135deg,var(--sps-accent-hover),var(--sps-accent))}\n"
        . "body.sps-root .fullscreen-btn{background:var(--sps-surface2);border-color:var(--sps-border);color:var(--sps-text)}\n"
        . "body.sps-root .fullscreen-btn:hover{background:var(--sps-accent);border-color:var(--sps-accent);color:#fff}\n"
        . "body.sps-root .renewal-container,body.sps-root .bell-container{background:linear-gradient(135deg,var(--sps-accent),var(--sps-accent-hover)) !important;border:1px solid transparent;box-shadow:0 4px 12px rgba(var(--sps-accent-rgb),.22);color:#fff !important}\n"
        . "body.sps-root .renewal-container>span:nth-of-type(2),body.sps-root .bell-container>span:nth-of-type(2){background:var(--sps-surface) !important}\n"
        . "body.sps-root .renewal-container i,body.sps-root .bell-container i{color:var(--sps-accent) !important}\n"
        . "body.sps-root .notification-badge{border-color:var(--sps-surface)}\n"
        . "body.sps-root .search-by-options,body.sps-root .search-results-dropdown,body.sps-root .renewals-dropdown,body.sps-root .activities-dropdown,body.sps-root .quick-action-dropdown,body.sps-root .qa-more-panel{background:var(--sps-surface);border-color:var(--sps-border);box-shadow:var(--sps-shadow);color:var(--sps-text)}\n"
        . "body.sps-root .search-option{color:var(--sps-text);border-color:var(--sps-border)}\n"
        . "body.sps-root .search-option:hover{background:var(--sps-soft)}\n"
        . "body.sps-root .search-option.selected{background:var(--sps-soft2);border-color:var(--sps-accent)}\n"
        . "body.sps-root .search-option span{color:var(--sps-text)}\n"
        . "body.sps-root .search-option:hover .option-icon,body.sps-root .option-icon{color:var(--sps-accent)}\n"
        . "body.sps-root .renewals-header,body.sps-root .activities-header{background:var(--sps-surface2);border-color:var(--sps-border);color:var(--sps-text)}\n"
        . "body.sps-root .renewals-header h6,body.sps-root .activities-header h6{color:var(--sps-text)}\n"
        . "body.sps-root .renewal-item{background:var(--sps-surface2);border-color:var(--sps-border);border-left-color:var(--sps-accent)}\n"
        . "body.sps-root .renewal-item:hover{background:var(--sps-surface3)}\n"
        . "body.sps-root .renewal-icon{background:var(--sps-surface3);border-color:var(--sps-border)}\n"
        . "body.sps-root .renewal-title,body.sps-root .renewal-title a{color:var(--sps-text) !important}\n"
        . "body.sps-root .renewal-user,body.sps-root .renewal-time{color:var(--sps-muted-text)}\n"
        . "body.sps-root .activity-item{border-color:var(--sps-border)}body.sps-root .activity-item:hover{background:var(--sps-surface2)}\n"
        . "body.sps-root .activity-icon{background:var(--sps-surface3);border-color:var(--sps-border)}\n"
        . "body.sps-root .activity-title{color:var(--sps-text)}\n"
        . "body.sps-root .activity-user,body.sps-root .activity-time{color:var(--sps-muted-text)}\n"
        . "body.sps-root .view-all-link{color:var(--sps-accent)}\n"
        . "body.sps-root .qa-subheader{background:var(--sps-surface2);border-color:var(--sps-border)}\n"
        . "body.sps-root .qa-header{color:var(--sps-muted-text)}\n"
        . "body.sps-root .qa-item{color:var(--sps-text)}\n"
        . "body.sps-root .qa-item:hover{background:var(--sps-soft);color:var(--sps-accent)}\n"
        . "body.sps-root .qa-divider{background:var(--sps-border)}\n"
        . "body.sps-root .qa-more{color:var(--sps-muted-text)}\n"
        . "body.sps-root .qa-more-arrow{color:var(--sps-muted-text)}\n"
        . "body.sps-root .qa-more.active .qa-more-arrow{color:var(--sps-accent)}\n"
        . "body.sps-root .qa-client-row{border-color:var(--sps-border)}\n"
        . "body.sps-root .qa-client-row:hover{background:var(--sps-surface2)}\n"
        . "body.sps-root .qa-client-row strong{color:var(--sps-text)}\n"
        . "body.sps-root .qa-client-row-bus,body.sps-root .qa-client-empty{color:var(--sps-muted-text)}\n";

    /* ── button / submit white-safe layer ────────────────────────────── */
    $c .= "body.sps-root .btn{color:var(--sps-text)}\n"
        . "body.sps-root .btn-light{background:var(--sps-surface2);border-color:var(--sps-border);color:var(--sps-text)}\n"
        . "body.sps-root .btn-light:hover,body.sps-root .btn-light:focus{background:var(--sps-surface3);color:var(--sps-text);border-color:var(--sps-border2)}\n"
        . "body.sps-root .btn-secondary{--bs-btn-bg:var(--sps-surface3);--bs-btn-border-color:var(--sps-border);--bs-btn-color:var(--sps-text);--bs-btn-hover-bg:var(--sps-accent);--bs-btn-hover-border-color:var(--sps-accent);--bs-btn-hover-color:#fff;--bs-btn-active-bg:var(--sps-accent-hover);--bs-btn-active-border-color:var(--sps-accent-hover);--bs-btn-active-color:#fff}\n"
        . "body.sps-root .btn-outline-secondary{--bs-btn-color:var(--sps-text);--bs-btn-border-color:var(--sps-border);--bs-btn-hover-bg:var(--sps-accent);--bs-btn-hover-border-color:var(--sps-accent);--bs-btn-hover-color:#fff;--bs-btn-active-bg:var(--sps-accent-hover);--bs-btn-active-border-color:var(--sps-accent-hover);--bs-btn-active-color:#fff}\n"
        . "body.sps-root .btn-white{background:var(--sps-surface)!important;border-color:var(--sps-border)!important;color:var(--sps-text)!important}\n"
        . "body.sps-root .btn-default{background:var(--sps-surface2);border-color:var(--sps-border);color:var(--sps-text)}\n"
        . "body.sps-root input[type=submit],body.sps-root button[type=submit]{transition:background .18s ease,box-shadow .18s ease,transform .12s ease}\n"
        . "body.sps-bg-dark input[type=submit]:not(.btn),body.sps-bg-dark button[type=submit]:not(.btn){background:linear-gradient(135deg,var(--sps-accent),var(--sps-accent-hover));border-color:transparent;color:#fff;border-radius:10px}\n"
        . "body.sps-bg-dark input[type=submit]:not(.btn):hover,body.sps-bg-dark button[type=submit]:not(.btn):hover{transform:translateY(-1px);box-shadow:0 6px 14px rgba(var(--sps-accent-rgb),.3)}\n"
        . "body.sps-root .card.bg-white,body.sps-root .bg-white{background:var(--sps-surface) !important}\n"
        . "body.sps-root .modal-content{background:var(--sps-surface);border-color:var(--sps-border);color:var(--sps-text)}\n"
        . "body.sps-root .dropdown-menu{background:var(--sps-surface);border-color:var(--sps-border);color:var(--sps-text)}\n"
        . "body.sps-root .dropdown-item{color:var(--sps-text)}body.sps-root .dropdown-item:hover,body.sps-root .dropdown-item:focus{background:var(--sps-surface3);color:var(--sps-text)}\n";

    /* ── glass (aurora) ──────────────────────────────────────────────── */
    if ($glass) {
        $c .= "body.sps-root.sps-glass .card,body.sps-root.sps-glass .aut-card{background:var(--sps-card);backdrop-filter:blur(18px) saturate(1.5);-webkit-backdrop-filter:blur(18px) saturate(1.5);border-color:var(--sps-border);box-shadow:var(--sps-shadow)}\n"
            . "body.sps-root.sps-glass .card-header,body.sps-root.sps-glass .card-h{background:var(--sps-surface2);backdrop-filter:blur(18px) saturate(1.5);-webkit-backdrop-filter:blur(18px) saturate(1.5)}\n"
            . "body.sps-root.sps-glass .dash-header{background:var(--sps-header-bg);backdrop-filter:blur(18px) saturate(1.4);-webkit-backdrop-filter:blur(18px) saturate(1.4)}\n"
            . "body.sps-root.sps-glass .dash-sidebar{background:var(--sps-sidebar-bg);backdrop-filter:blur(22px) saturate(1.5);-webkit-backdrop-filter:blur(22px) saturate(1.5)}\n"
            . "body.sps-root.sps-glass .dropdown-menu,body.sps-root.sps-glass .modal-content{background:rgba(15,20,38,.92);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px)}\n"
            . "@keyframes spsAurora1{0%{transform:translate(0,0) scale(1)}50%{transform:translate(6%,10%) scale(1.15)}100%{transform:translate(0,0) scale(1)}}\n"
            . "@keyframes spsAurora2{0%{transform:translate(0,0) scale(1)}50%{transform:translate(-8%,-6%) scale(1.1)}100%{transform:translate(0,0) scale(1)}}\n"
            . "body.sps-root.sps-glass::before,body.sps-root.sps-glass::after{content:'';position:fixed;z-index:-1;width:52vw;height:52vw;border-radius:50%;filter:blur(90px);opacity:.55;pointer-events:none}\n"
            . "body.sps-root.sps-glass::before{top:-12vw;right:-8vw;background:radial-gradient(circle,var(--sps-accent),transparent 65%);animation:spsAurora1 18s ease-in-out infinite}\n"
            . "body.sps-root.sps-glass::after{bottom:-14vw;left:-10vw;background:radial-gradient(circle,#3b82f6,transparent 65%);animation:spsAurora2 22s ease-in-out infinite;opacity:.4}\n";
    }

    /* ── radius ──────────────────────────────────────────────────────── */
    if ($cfg['radius'] === 'square') {
        $c .= "body.sps-root.sps-radius-square .card,body.sps-root.sps-radius-square .btn,"
            . "body.sps-root.sps-radius-square .form-control,body.sps-root.sps-radius-square .form-select,"
            . "body.sps-root.sps-radius-square .dash-header:not(.transprent-bg),body.sps-root.sps-radius-square .dash-sidebar.light-sidebar:not(.transprent-bg),"
            . "body.sps-root.sps-radius-square .badge,body.sps-root.sps-radius-square .aut-card,body.sps-root.sps-radius-square .input-group-text,"
            . "body.sps-root.sps-radius-square .modal-content,body.sps-root.sps-radius-square .dropdown-menu{border-radius:0 !important}\n";
    } elseif ($cfg['radius'] === 'rounded') {
        $r = $cfg['preset'] === 'neon' ? '10px' : ($cfg['preset'] === 'aurora' ? '18px' : '16px');
        $c .= "body.sps-root.sps-radius-rounded .card,body.sps-root.sps-radius-rounded .btn,"
            . "body.sps-root.sps-radius-rounded .form-control,body.sps-root.sps-radius-rounded .form-select,"
            . "body.sps-root.sps-radius-rounded .badge,body.sps-root.sps-radius-rounded .aut-card,"
            . "body.sps-root.sps-radius-rounded .modal-content,body.sps-root.sps-radius-rounded .dropdown-menu{border-radius:{$r} !important}\n";
    }

    /* ── density & font scale ────────────────────────────────────────── */
    if ($cfg['compact'] === '1') {
        $c .= ".sps-compact{font-size:13.5px}"
            . ".sps-compact .dash-header{min-height:58px}"
            . ".sps-compact .header-wrapper .nav-link{padding:.35rem .55rem}"
            . ".sps-compact .dash-sidebar .dash-link{padding:10px 16px}"
            . ".sps-compact .card{padding:0}.sps-compact .card .card-body{padding:.9rem}"
            . ".sps-compact .card-header,.sps-compact .card-h{padding:12px 16px}"
            . ".sps-compact .page-header{margin-bottom:18px}"
            . ".sps-compact .form-control,.sps-compact .form-select{min-height:34px}\n";
    }
    if ($cfg['font'] === 'large') {
        $c .= ".sps-font-lg,.sps-font-lg body{font-size:16.5px}"
            . ".sps-font-lg .dash-mtext,.sps-font-lg .form-label,.sps-font-lg label{font-size:14.5px}\n";
    }

    /* ── typography (font family / size / heading weight / leading) ──── */
    $fstack = [
        'system'    => 'system-ui,-apple-system,\'Segoe UI\',Roboto,\'Helvetica Neue\',Arial,sans-serif',
        'open-sans' => '\'Open Sans\',system-ui,-apple-system,\'Segoe UI\',Roboto,sans-serif',
        'inter'     => '\'Inter\',system-ui,-apple-system,\'Segoe UI\',Roboto,sans-serif',
        'poppins'   => '\'Poppins\',\'Segoe UI\',system-ui,sans-serif',
        'roboto'    => '\'Roboto\',\'Segoe UI\',system-ui,sans-serif',
        'nunito'    => '\'Nunito\',\'Segoe UI\',system-ui,sans-serif',
    ];
    if (isset($fstack[$cfg['font_family']])) {
        $c .= "body.sps-root,body.sps-root body,body.sps-root input,body.sps-root button,body.sps-root select,body.sps-root textarea{font-family:{$fstack[$cfg['font_family']]}}\n"
            . "body.sps-root h1,body.sps-root .h1,body.sps-root h2,body.sps-root .h2,body.sps-root h3,body.sps-root .h3,"
            . "body.sps-root h4,body.sps-root .h4,body.sps-root h5,body.sps-root .h5,body.sps-root h6,body.sps-root .h6{font-family:var(--bs-font-sans-serif);font-weight:{$cfg['heading_weight']}}\n";
    } else {
        $c .= "body.sps-root h1,body.sps-root .h1,body.sps-root h2,body.sps-root .h2,body.sps-root h3,body.sps-root .h3,"
            . "body.sps-root h4,body.sps-root .h4,body.sps-root h5,body.sps-root .h5,body.sps-root h6,body.sps-root .h6{font-weight:{$cfg['heading_weight']}}\n";
    }
    $szMap = ['xs'=>12, 'sm'=>13, 'normal'=>14, 'lg'=>15.5, 'xl'=>17];
    if ($cfg['font_size'] !== 'normal') {
        $px = $szMap[$cfg['font_size']];
        $c .= "body.sps-root,body.sps-root body{font-size:{$px}px}\n"
            . "body.sps-root .dash-mtext,body.sps-root .form-label,body.sps-root label{font-size:" . round($px - 1, 1) . "px}\n";
    }
    $lhMap = ['tight'=>1.35, 'normal'=>1.5, 'relaxed'=>1.7];
    if ($cfg['line_height'] !== 'normal') {
        $c .= "body.sps-root,body.sps-root body,body.sps-root p,body.sps-root .form-control,body.sps-root .form-select,body.sps-root .dash-mtext{line-height:{$lhMap[$cfg['line_height']]}}\n";
    }
    $lsMap = ['tight'=>'-0.02em', 'normal'=>'0', 'wide'=>'0.045em'];
    if ($cfg['letter_spacing'] !== 'normal') {
        $c .= "body.sps-root,body.sps-root body{letter-spacing:{$lsMap[$cfg['letter_spacing']]}}\n"
            . "body.sps-root h1,body.sps-root h2,body.sps-root h3,body.sps-root h4,body.sps-root h5,body.sps-root h6{letter-spacing:{$lsMap[$cfg['letter_spacing']]}}\n";
    }

    /* ── scrollbars ──────────────────────────────────────────────────── */
    $c .= "body.sps-root *::-webkit-scrollbar{width:10px;height:10px}"
        . "body.sps-root *::-webkit-scrollbar-track{background:transparent}"
        . "body.sps-root *::-webkit-scrollbar-thumb{background:var(--sps-border2);border-radius:8px;border:2px solid transparent;background-clip:content-box}"
        . "body.sps-root *::-webkit-scrollbar-thumb:hover{background:var(--sps-accent);border:2px solid transparent;background-clip:content-box}\n"
        . "body.sps-root{scrollbar-color:var(--sps-border2) transparent}\n";

    /* ── focus ring ──────────────────────────────────────────────────── */
    $c .= "body.sps-root a:focus-visible,body.sps-root button:focus-visible,body.sps-root [tabindex]:focus-visible,body.sps-root input:focus-visible,body.sps-root select:focus-visible{outline:2px solid var(--sps-accent);outline-offset:2px;border-radius:4px}\n";

    /* ── motion (page intro) ─────────────────────────────────────────── */
    if ($cfg['effects'] === '1' && $cfg['motion'] === 'on') {
        $c .= "@media (prefers-reduced-motion:no-preference){"
            . "body.sps-root.sps-motion .dash-content>*{animation:spsUp .45s ease both}"
            . "body.sps-root.sps-motion .dash-content>*:nth-child(2){animation-delay:.04s}"
            . "body.sps-root.sps-motion .dash-content>*:nth-child(3){animation-delay:.08s}"
            . "body.sps-root.sps-motion .dash-content>*:nth-child(4){animation-delay:.12s}"
            . "body.sps-root.sps-motion .page-header{animation:spsUp .4s ease both}"
            . "@keyframes spsUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}}\n";
    }

    /* ── hover micro-interactions ────────────────────────────────────── */
    if ($cfg['effects'] === '1') {
        $c .= "@media (prefers-reduced-motion:no-preference){"
            . "body.sps-root.sps-effects .card:not(.transprent-bg):hover,body.sps-root.sps-effects .aut-card:hover{transform:translateY(-2px);box-shadow:var(--sps-shadow),0 12px 28px rgba(var(--sps-accent-rgb),.12);transition:transform .2s ease,box-shadow .2s ease}"
            . "body.sps-root.sps-effects .dash-sidebar .dash-link{transition:background .18s ease,color .18s ease,box-shadow .18s ease}"
            . "body.sps-root.sps-effects .btn-primary{transition:background .18s ease,box-shadow .18s ease,transform .12s ease}"
            . "body.sps-root.sps-effects .btn-primary:hover{transform:translateY(-1px)}\n";
        if ($neon) $c .= "body.sps-root.sps-effects.sps-p-neon .btn-primary:hover{box-shadow:0 8px 26px rgba(var(--sps-accent-rgb),.55)}\n";
        $c .= "}\n";
    }

    /* ── custom cursor ───────────────────────────────────────────────── */
    if ($cfg['effects'] === '1' && $cfg['cursor'] !== 'off') {
        $c .= "@media (prefers-reduced-motion:no-preference) and (pointer:fine){
#spsCurDot{position:fixed;left:0;top:0;width:7px;height:7px;border-radius:50%;background:var(--sps-accent);pointer-events:none;z-index:2147483647;transform:translate(-50%,-50%);transition:opacity .2s}
#spsCurRing{position:fixed;left:0;top:0;width:34px;height:34px;border-radius:50%;border:1.5px solid var(--sps-accent);pointer-events:none;z-index:2147483646;transform:translate(-50%,-50%);transition:width .16s ease,height .16s ease,opacity .2s,border-color .2s}
body.sps-cursor-dot #spsCurRing,body.sps-cursor-ring #spsCurRing{opacity:.85}
body.sps-cursor-aura #spsCurRing{width:56px;height:56px;border:1px solid rgba(var(--sps-accent-rgb),.35);background:rgba(var(--sps-accent-rgb),.08);backdrop-filter:blur(2px)}
body.sps-cursor-aura #spsCurDot{display:none}
body.sps-cursor-cross #spsCurDot{display:none}
body.sps-cursor-cross #spsCurRing{width:18px;height:18px;border:1.6px solid var(--sps-accent);border-radius:0;transform:translate(-50%,-50%) rotate(45deg);opacity:.95;box-shadow:0 0 3px rgba(var(--sps-accent-rgb),.35)}
body.sps-cursor-comet #spsCurDot{width:9px;height:9px;background:var(--sps-accent);box-shadow:0 0 10px 3px rgba(var(--sps-accent-rgb),.55)}
body.sps-cursor-comet #spsCurRing{width:64px;height:64px;border:none;border-radius:50%;background:radial-gradient(circle,transparent 30%,rgba(var(--sps-accent-rgb),.16) 62%,transparent 100%);opacity:.9}
body.sps-cursor-pulse #spsCurRing{width:26px;height:26px;border:2px solid rgba(var(--sps-accent-rgb),.55);animation:spsCurPulse 1.5s ease-out infinite;transition:none}
body.sps-cursor-pulse #spsCurDot{width:5px;height:5px}
@keyframes spsCurPulse{0%{width:26px;height:26px;opacity:.95}100%{width:56px;height:56px;opacity:0}}
body.sps-cursor-on a,body.sps-cursor-on .btn,body.sps-cursor-on .dash-link,body.sps-cursor-on button,body.sps-cursor-on input,body.sps-cursor-on select,body.sps-cursor-on textarea,body.sps-cursor-on [contenteditable]{cursor:none}
#spsCurRing.sps-cur-hover{width:46px;height:46px;border-color:var(--sps-accent)}
}\n";
    }

    /* ── body background override: uploaded image or custom colour ──────
       Emitted LAST so it wins over the preset gradient + dark sweep
       (both use body.sps-* with !important). */
    if ($t['bg_cover'] !== '') {
        $dim = max(0, min(85, (int)$t['bg_cover_dim']));
        $ov  = $dim > 0 ? 'linear-gradient(rgba(7,10,18,' . round($dim / 100, 2) . '),rgba(7,10,18,' . round($dim / 100, 2) . ')),' : '';
        $c  .= "body.sps-root{background:{$ov}url(uploads/theme_bg/{$t['bg_cover']}) center/cover no-repeat fixed!important;background-color:var(--sps-bg)}\n";
    } elseif ($t['bg_color'] !== '') {
        $c  .= "body.sps-root{background:{$t['bg_color']} !important}\n";
    }

    return $c;
}

/* ── boot script (classes + effects + cursor) ──────────────────────────── */
function sps_theme_boot() {
    return '<script id="sps-theme-boot">' . "\n"
        . '(function(){' . "\n"
        . 'function spsCfg(){try{return JSON.parse(document.getElementById("sps-theme-cfg").textContent||"{}")}catch(e){return{}}}' . "\n"
        . 'var cfg=spsCfg();' . "\n"
        . 'function spsApply(c){' . "\n"
        . '  var b=document.body; if(!b) return;' . "\n"
        . '  var i, p=c.preset||"classic";' . "\n"
        . '  for(i=1;i<=10;i++) b.classList.remove("theme-"+i);' . "\n"
        . '  b.classList.add("sps-root","sps-p-"+p);' . "\n"
        . '  var dark=(c.mode==="dark")||(p==="custom"&&c.bg==="dark")||c.bg==="dark";' . "\n"
        . '  b.classList.toggle("sps-bg-dark", !!dark);' . "\n"
        . '  b.classList.toggle("sps-glass", p==="aurora");' . "\n"
        . '  b.classList.toggle("sps-glow", !!(c.glow||0)||p==="neon"||p==="midnight");' . "\n"
        . '  b.classList.toggle("sps-radius-square", c.radius==="square");' . "\n"
        . '  b.classList.toggle("sps-radius-rounded", c.radius==="rounded");' . "\n"
        . '  b.classList.toggle("sps-compact", c.compact==="1");' . "\n"
        . '  b.classList.toggle("sps-font-lg", c.font==="large");' . "\n"
        . '  var bst=["filled","soft","outline","gradient"], bs=c.btn_style||"filled";' . "\n"
        . '  for(i=0;i<bst.length;i++) b.classList.remove("sps-btn-"+bst[i]);' . "\n"
        . '  b.classList.add("sps-btn-"+(bst.indexOf(bs)>-1?bs:"filled"));' . "\n"
        . '  var fine=window.matchMedia&&matchMedia("(pointer:fine)").matches;' . "\n"
        . '  var rm=matchMedia("(prefers-reduced-motion:reduce)").matches;' . "\n"
        . '  b.classList.toggle("sps-effects", c.effects==="1"&&fine&&!rm);' . "\n"
        . '  b.classList.toggle("sps-motion", c.effects==="1"&&c.motion==="on"&&!rm);' . "\n"
        . '  b.classList.remove("sps-cursor-dot","sps-cursor-ring","sps-cursor-aura","sps-cursor-cross","sps-cursor-comet","sps-cursor-pulse","sps-cursor-on");' . "\n"
        . '  if(c.effects==="1"&&fine&&!rm&&c.cursor&&c.cursor!=="off"){b.classList.add("sps-cursor-on","sps-cursor-"+c.cursor);spsCursor&&spsCursor.start();}else{spsCursor&&spsCursor.stop();}' . "\n"
        . '}' . "\n"
        /* cursor engine */
        . 'var spsCursor=(function(){var dot,ring,on=false,x=0,y=0,dx=0,dy=0,raf=null,nx=0,ny=0;' . "\n"
        . 'function el(s){var e=document.getElementById(s);if(!e){e=document.createElement("div");e.id=s;document.body.appendChild(e);}return e;}' . "\n"
        . 'function step(){var d=0.18;dx+=(x-dx)*d;dy+=(y-dy)*d;dot.style.transform="translate("+(nx)+"px,"+(ny)+"px) translate(-50%,-50%)";ring.style.transform="translate("+(dx)+"px,"+(dy)+"px) translate(-50%,-50%)";if(on)raf=requestAnimationFrame(step);}' . "\n"
        . 'return{' . "\n"
        . 'start:function(){if(on)return;on=true;dot=el("spsCurDot");ring=el("spsCurRing");document.addEventListener("mousemove",mv);document.addEventListener("mouseover",ot);document.addEventListener("mouseout",out);document.addEventListener("mouseleave",hide);step();},' . "\n"
        . 'stop:function(){if(!on)return;on=false;document.removeEventListener("mousemove",mv);document.removeEventListener("mouseover",ot);document.removeEventListener("mouseout",out);document.removeEventListener("mouseleave",hide);var d=document.getElementById("spsCurDot"),r=document.getElementById("spsCurRing");if(d)d.remove();if(r)r.remove();}' . "\n"
        . '};' . "\n"
        . 'function mv(e){x=e.clientX;y=e.clientY;nx=e.clientX;ny=e.clientY;dot.style.opacity="1";ring.style.opacity="1";}' . "\n"
        . 'function ot(e){var t=e.target.closest("a,.btn,.dash-link,button,input,select,textarea");if(t)ring.classList.add("sps-cur-hover");}' . "\n"
        . 'function out(e){var t=e.target.closest("a,.btn,.dash-link,button,input,select,textarea");if(t)ring.classList.remove("sps-cur-hover");}' . "\n"
        . 'function hide(){dot.style.opacity="0";ring.style.opacity="0";}' . "\n"
        . '})();' . "\n"
        . 'if(document.body) spsApply(cfg); else document.addEventListener("DOMContentLoaded",function(){spsApply(cfg);});' . "\n"
        . 'window.__spsApply=spsApply;' . "\n"
        . '})();' . "\n"
        . '</script>' . "\n";
}

function sps_theme_inject($con) {
    $cfg = sps_theme_config($con);
    $p = sps_palette($cfg);
    $cfgOut = $cfg;
    $cfgOut['mode'] = $p['mode'];
    $cfgOut['glow'] = $p['glow'] ? '1' : '0';
    /* local web fonts loaded on demand so only the chosen family ships */
    $webFonts = [
        'inter' => 'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap',
        'poppins' => 'https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap',
        'roboto' => 'https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap',
        'nunito' => 'https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;500;600;700;800&display=swap',
    ];
    $fontLink = isset($webFonts[$cfg['font_family']])
        ? '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link rel="stylesheet" href="' . $webFonts[$cfg['font_family']] . '">'
        : '';
    echo "\n" . $fontLink . '<style id="sps-theme-css">' . sps_theme_css($cfg) . '</style>' . "\n"
       . '<script type="application/json" id="sps-theme-cfg">' . json_encode($cfgOut, JSON_UNESCAPED_SLASHES) . '</script>' . "\n"
       . sps_theme_boot();
}

} /* end function_exists guard */