<?php
// ============================================================
// SPS CRM - Users
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/layout.php';

require_login();
require_permission('admin.users');

$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add') {
    verify_csrf();

    $username = trim($_POST['username'] ?? '');
    $first = trim($_POST['first_name'] ?? '');
    $last  = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role_id = (int)($_POST['role_id'] ?? 0) ?: null;

    $temp_password = bin2hex(random_bytes(6));

    if (!$username || !$first || !$last) {
        flash_error(
            'Username, first name, and last name are required.'
        );
    } elseif (
        db_row(
            "SELECT id FROM users WHERE username=?",
            [$username]
        )
    ) {
        flash_error(
            'That username is already taken.'
        );
    } else {
        $uid = db_insert(
            "INSERT INTO users
            (
                username,
                password_hash,
                first_name,
                last_name,
                email,
                role_id,
                is_active,
                must_change_password,
                created_at,
                updated_at
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                1,
                1,
                NOW(),
                NOW()
            )",
            [
                $username,
                password_hash(
                    $temp_password,
                    PASSWORD_DEFAULT
                ),
                $first,
                $last,
                $email ?: null,
                $role_id
            ]
        );

        log_activity(
            'create',
            'users',
            $uid,
            "User created: $username",
            null,
            2
        );

        flash_success(
            "User created. Temporary password: $temp_password " .
            "(share this securely — it won't be shown again)."
        );
    }

    redirect('users.php');
}

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    $action === 'toggle_active'
) {
    verify_csrf();

    $uid = (int)($_POST['user_id'] ?? 0);

    if ($uid !== current_user_id()) {
        db_query(
            "UPDATE users
             SET is_active = 1 - is_active
             WHERE id=?",
            [$uid]
        );

        log_activity(
            'update',
            'users',
            $uid,
            'User active status toggled',
            null,
            2
        );
    }

    redirect('users.php');
}

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    $action === 'change_role'
) {
    verify_csrf();

    $uid = (int)($_POST['user_id'] ?? 0);
    $role_id = (int)($_POST['role_id'] ?? 0) ?: null;

    db_query(
        "UPDATE users
         SET role_id=?
         WHERE id=?",
        [$role_id, $uid]
    );

    log_activity(
        'update',
        'users',
        $uid,
        'Role changed',
        null,
        2
    );

    redirect('users.php');
}

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    $action === 'reset_password'
) {
    verify_csrf();

    $uid = (int)($_POST['user_id'] ?? 0);

    $temp_password = bin2hex(random_bytes(6));

    db_query(
        "UPDATE users
         SET password_hash=?,
             must_change_password=1,
             updated_at=NOW()
         WHERE id=?",
        [
            password_hash(
                $temp_password,
                PASSWORD_DEFAULT
            ),
            $uid
        ]
    );

    log_activity(
        'update',
        'users',
        $uid,
        'Password reset by admin',
        null,
        2
    );

    flash_success(
        "Password reset. Temporary password: $temp_password " .
        "(share this securely — it won't be shown again)."
    );

    redirect('users.php');
}

$users = db_rows(
    "SELECT u.*, r.name as role_name
     FROM users u
     LEFT JOIN roles r ON r.id=u.role_id
     ORDER BY u.first_name"
);

$roles = db_rows(
    "SELECT * FROM roles ORDER BY name"
);

render_header('Users', 'users');
?>

<?= render_flash() ?>

<div class="card">
  <div class="card-header">
    <h2>Users</h2>
    <button
        class="btn btn-primary btn-sm"
        onclick="openModal('add-user-modal')">
        + Add User
    </button>
  </div>

  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>Name</th>
          <th>Username</th>
          <th>Email</th>
          <th>Role</th>
          <th>Last Login</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>

      <tbody>
      <?php foreach ($users as $u): ?>
      <tr>

        <td style="font-weight:500">
          <?= e($u['first_name'].' '.$u['last_name']) ?>
        </td>

        <td>
          <?= e($u['username']) ?>
        </td>

        <td>
          <?= e($u['email'] ?: '—') ?>
        </td>

        <td>
          <form method="POST" style="display:inline">
            <?= csrf_field() ?>

            <input
                type="hidden"
                name="action"
                value="change_role">

            <input
                type="hidden"
                name="user_id"
                value="<?= $u['id'] ?>">

            <select
                name="role_id"
                class="form-control"
                style="padding:3px 6px;font-size:12px"
                onchange="this.form.submit()"
                <?= $u['id']==current_user_id()?'disabled':'' ?>>

              <option value="">
                — None —
              </option>

              <?php foreach ($roles as $r): ?>
              <option
                  value="<?= $r['id'] ?>"
                  <?= $u['role_id']==$r['id']?'selected':'' ?>>
                  <?= e($r['name']) ?>
              </option>
              <?php endforeach; ?>

            </select>
          </form>
        </td>

        <td class="text-muted">
          <?= $u['last_login']
              ? fmt_datetime($u['last_login'])
              : 'Never' ?>
        </td>

        <td>
          <?= $u['is_active']
              ? '<span class="badge badge-success">Active</span>'
              : '<span class="badge badge-secondary">Disabled</span>' ?>
        </td>

        <td style="white-space:nowrap">

          <form
              method="POST"
              style="display:inline"
              onsubmit="return confirm('Reset password for <?= e($u['username']) ?>?')">

            <?= csrf_field() ?>

            <input
                type="hidden"
                name="action"
                value="reset_password">

            <input
                type="hidden"
                name="user_id"
                value="<?= $u['id'] ?>">

            <button class="btn btn-xs btn-secondary">
              Reset Pwd
            </button>

          </form>

          <?php if ($u['id'] != current_user_id()): ?>

          <form method="POST" style="display:inline">

            <?= csrf_field() ?>

            <input
                type="hidden"
                name="action"
                value="toggle_active">

            <input
                type="hidden"
                name="user_id"
                value="<?= $u['id'] ?>">

            <button
                class="btn btn-xs <?= $u['is_active']
                    ? 'btn-danger'
                    : 'btn-secondary' ?>">

              <?= $u['is_active']
                  ? 'Disable'
                  : 'Enable' ?>

            </button>

          </form>

          <?php endif; ?>

        </td>

      </tr>
      <?php endforeach; ?>
      </tbody>

    </table>
  </div>
</div>

<div class="modal-overlay" id="add-user-modal">

  <div class="modal">

    <div class="modal-header">
      <h3>Add User</h3>

      <button
          class="modal-close"
          onclick="closeModal('add-user-modal')">
          ×
      </button>
    </div>

    <form method="POST">

      <?= csrf_field() ?>

      <input
          type="hidden"
          name="action"
          value="add">

      <div class="modal-body">

        <div class="form-row">

          <div class="form-group">
            <label>
              First Name
              <span class="text-danger">*</span>
            </label>

            <input
                type="text"
                name="first_name"
                class="form-control"
                required>
          </div>

          <div class="form-group">
            <label>
              Last Name
              <span class="text-danger">*</span>
            </label>

            <input
                type="text"
                name="last_name"
                class="form-control"
                required>
          </div>

        </div>

        <div class="form-group">

          <label>
            Username
            <span class="text-danger">*</span>
          </label>

          <input
              type="text"
              name="username"
              class="form-control"
              required>

        </div>

        <div class="form-group">

          <label>Email</label>

          <input
              type="email"
              name="email"
              class="form-control">

        </div>

        <div class="form-group">

          <label>Role</label>

          <select
              name="role_id"
              class="form-control">

            <option value="">
              — None —
            </option>

            <?php foreach ($roles as $r): ?>

            <option value="<?= $r['id'] ?>">
              <?= e($r['name']) ?>
            </option>

            <?php endforeach; ?>

          </select>

        </div>

        <div class="alert alert-info">
          A random temporary password will be generated and shown once
          after creation. The user must change it on first login.
        </div>

      </div>

      <div class="modal-footer">

        <button
            type="button"
            class="btn btn-secondary"
            onclick="closeModal('add-user-modal')">
            Cancel
        </button>

        <button
            type="submit"
            class="btn btn-primary">
            Create User
        </button>

      </div>

    </form>

  </div>

</div>

<?php render_footer(); ?>