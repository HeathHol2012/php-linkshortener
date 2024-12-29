/*
<!--
==============================================
    NOTES & COMMENTS SECTION 
==============================================
    HI i see your using inspect element (or reading my code)
    Please dont change any of the code here on inspect element but feelfree to download and edit the code offline

    
    notes: 
    - version 2.0 coded by heath github user: heathhol2012
    - runns on php so use a local server like ISS or alpache
    - my website will be running this (http://shortify.us.kg/)
    - on server side the usernames and passwords are stored in user.json
    - on server side the links are stored in links.json
    - when upgrading to a newer version of this you can just change the index.php and keep the links.json and user.json
    - if you have any issues with this please make a issue on github and i will try to help!
    -defualt username and password: admin admin (you can change in admin panel)
==============================================
Changelog for Version 2.0:
- Added CSRF token generation and validation for enhanced security.
- Implemented file-based storage for users and links.a
- Created functions for loading and saving data from/to JSON files.
- Added URL normalization to ensure proper formatting of links.
- Initialized storage files if they do not exist.
*/
<?php
session_start();

// Initialize error message variable
$login_error = '';

// File-based storage since SQL is not allowed
$users_file = 'users.json';
$links_file = 'links.json';

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Verify CSRF token on POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('CSRF token validation failed');
    }
}

// Initialize storage files if they don't exist
if (!file_exists($users_file)) {
    file_put_contents($users_file, json_encode(['admin' => [
        'password' => password_hash('admin', PASSWORD_DEFAULT),
        'is_admin' => true,
        'link_limit' => PHP_INT_MAX
    ]]));
}

if (!file_exists($links_file)) {
    file_put_contents($links_file, json_encode([]));
}

function load_data($file) {
    return json_decode(file_get_contents($file), true);
}

function save_data($file, $data) {
    file_put_contents($file, json_encode($data));
}

function normalize_url($url) {
    if (!preg_match('~^(?:f|ht)tps?://~i', $url)) {
        return 'http://' . $url;
    }
    return $url;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['login'])) {
        $users = load_data($users_file);
        $username = htmlspecialchars($_POST['username']);
        $password = $_POST['password'];
        
        if (!isset($users[$username])) {
            $login_error = "Username not found. Please check your username and try again.";
        } else if (!password_verify($password, $users[$username]['password'])) {
            $login_error = "Incorrect password. Please try again.";
        } else {
            $_SESSION['user'] = $username;
            $_SESSION['is_admin'] = $users[$username]['is_admin'] ?? false;
        }
    }
    
    if (isset($_POST['logout'])) {
        session_destroy();
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    
    if (isset($_POST['create_link']) && isset($_SESSION['user'])) {
        $links = load_data($links_file);
        $suffix = htmlspecialchars($_POST['suffix']);
        
        if (!isset($links[$suffix])) {
            $users = load_data($users_file);
            if ($users[$_SESSION['user']]['link_limit'] > 0) {
                $links[$suffix] = [
                    'url' => normalize_url(htmlspecialchars($_POST['url'])),
                    'owner' => htmlspecialchars($_SESSION['user']),
                    'clicks' => 0
                ];
                $users[$_SESSION['user']]['link_limit']--;
                save_data($links_file, $links);
                save_data($users_file, $users);
            }
        }
    }

    if (isset($_POST['update_admin']) && $_SESSION['is_admin']) {
        $users = load_data($users_file);
        $new_admin_username = htmlspecialchars($_POST['new_admin_username']);
        $new_admin_password = $_POST['new_admin_password'];
        
        $users[$new_admin_username] = [
            'password' => password_hash($new_admin_password, PASSWORD_DEFAULT),
            'is_admin' => true,
            'link_limit' => PHP_INT_MAX
        ];
        
        if ($new_admin_username !== 'admin') {
            unset($users['admin']);
        }
        
        save_data($users_file, $users);
        $_SESSION['user'] = $new_admin_username;
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    if (isset($_POST['delete_link'])) {
        $links = load_data($links_file);
        $suffix_to_delete = htmlspecialchars($_POST['delete_suffix']);
        
        if ($_SESSION['is_admin'] || 
            ($links[$suffix_to_delete]['owner'] === $_SESSION['user'])) {
            unset($links[$suffix_to_delete]);
            save_data($links_file, $links);
        }
    }

    if (isset($_POST['edit_link'])) {
        $links = load_data($links_file);
        $old_suffix = htmlspecialchars($_POST['old_suffix']);
        $new_suffix = htmlspecialchars($_POST['new_suffix']);
        $new_url = htmlspecialchars($_POST['new_url']);
        
        if ($_SESSION['is_admin'] || 
            ($links[$old_suffix]['owner'] === $_SESSION['user'])) {
            
            $link_data = $links[$old_suffix];
            $link_data['url'] = normalize_url($new_url);
            
            if ($old_suffix !== $new_suffix) {
                unset($links[$old_suffix]);
                $links[$new_suffix] = $link_data;
            } else {
                $links[$old_suffix] = $link_data;
            }
            
            save_data($links_file, $links);
        }
    }
    
    if (isset($_POST['add_user']) && $_SESSION['is_admin']) {
        $users = load_data($users_file);
        $new_username = htmlspecialchars($_POST['new_username']);
        if (!isset($users[$new_username])) {
            $users[$new_username] = [
                'password' => password_hash($_POST['new_password'], PASSWORD_DEFAULT),
                'is_admin' => false,
                'link_limit' => (int)$_POST['link_limit']
            ];
            save_data($users_file, $users);
        }
    }
    
    if (isset($_POST['delete_user']) && $_SESSION['is_admin']) {
        $username_to_delete = htmlspecialchars($_POST['delete_username']);
        $users = load_data($users_file);
        $links = load_data($links_file);

        foreach ($links as $suffix => $link) {
            if ($link['owner'] === $username_to_delete) {
                unset($links[$suffix]);
            }
        }
        
        unset($users[$username_to_delete]);
        
        save_data($users_file, $users);
        save_data($links_file, $links);
        
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Handle link redirects and click tracking
if (isset($_GET['code'])) {
    $code = $_GET['code'];
    $links = load_data($links_file);
    if (isset($links[$code])) {
        $links[$code]['clicks'] = ($links[$code]['clicks'] ?? 0) + 1;
        save_data($links_file, $links);
        header('Location: ' . $links[$code]['url']);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modern Link Shortener</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #6B73FF 0%, #000DFF 100%);
            min-height: 100vh;
            padding: 2rem;
        }
        .card {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 15px;
            backdrop-filter: blur(10px);
            border: none;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }
        .btn-primary {
            background: #000DFF;
            border: none;
            padding: 0.5rem 1.5rem;
            border-radius: 8px;
        }
        .btn-primary:hover {
            background: #0007CC;
            transform: translateY(-2px);
            transition: all 0.3s ease;
        }
        .form-control {
            border-radius: 8px;
            border: 1px solid #E0E0E0;
            padding: 0.75rem;
        }
        .table {
            border-radius: 8px;
            overflow: hidden;
        }
        .stats-card {
            background: linear-gradient(45deg, #FF6B6B, #FF8E53);
            color: white;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container">
        <?php if (!isset($_SESSION['user'])): ?>
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card p-4">
                        <h2 class="text-center mb-4">Welcome to Link Shortener</h2>
                        <?php if (!empty($login_error)): ?>
                            <div class="alert alert-danger"><?php echo $login_error; ?></div>
                        <?php endif; ?>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <div class="mb-3">
                                <input type="text" class="form-control" name="username" placeholder="Username" required>
                            </div>
                            <div class="mb-3">
                                <input type="password" class="form-control" name="password" placeholder="Password" required>
                            </div>
                            <button type="submit" name="login" class="btn btn-primary w-100">Login</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="row">
                <div class="col-12 mb-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <h2>Welcome, <?php echo htmlspecialchars($_SESSION['user']); ?></h2>
                        <form method="post" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <button type="submit" name="logout" class="btn btn-outline-danger">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <?php if ($_SESSION['is_admin']): ?>
                <div class="card mb-4">
                    <div class="card-body">
                        <h3>Admin Panel</h3>
                        <ul class="nav nav-tabs" id="adminTabs" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" data-bs-toggle="tab" href="#users">Manage Users</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#links">All Links</a>
                            </li>
                        </ul>

                        <div class="tab-content mt-3">
                            <div class="tab-pane fade show active" id="users">
                                <form method="post" class="mb-4">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <input type="text" name="new_username" class="form-control" placeholder="Username" required>
                                        </div>
                                        <div class="col-md-4">
                                            <input type="password" name="new_password" class="form-control" placeholder="Password" required>
                                        </div>
                                        <div class="col-md-2">
                                            <input type="number" name="link_limit" class="form-control" placeholder="Link Limit" required>
                                        </div>
                                        <div class="col-md-2">
                                            <button type="submit" name="add_user" class="btn btn-primary w-100">Add User</button>
                                        </div>
                                    </div>
                                </form>

                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Username</th>
                                                <th>Link Limit</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($users as $username => $user): ?>
                                                <?php if ($username !== $_SESSION['user']): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($username); ?></td>
                                                        <td><?php echo htmlspecialchars($user['link_limit']); ?></td>
                                                        <td>
                                                            <form method="post" class="d-inline">
                                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                                <input type="hidden" name="delete_username" value="<?php echo htmlspecialchars($username); ?>">
                                                                <button type="submit" name="delete_user" class="btn btn-danger btn-sm">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="links">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Short URL</th>
                                                <th>Original URL</th>
                                                <th>Owner</th>
                                                <th>Clicks</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            // Load the links data from the specified file
                                            $links = load_data($links_file);
                                            // Iterate through each link and display its details
                                            foreach ($links as $code => $link): 
                                            ?>
                                                <tr>
                                                    <td>
                                                        <a href="?code=<?php echo htmlspecialchars($code); ?>" target="_blank">
                                                            <?php echo htmlspecialchars($_SERVER['HTTP_HOST'] . '/?code=' . $code); ?>
                                                        </a>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($link['url']); ?></td>
                                                    <td><?php echo htmlspecialchars($link['owner']); ?></td>
                                                    <td><?php echo htmlspecialchars($link['clicks'] ?? 0); ?></td>
                                                    <td>
                                                        <button class="btn btn-primary btn-sm" onclick="showEditModal('<?php echo htmlspecialchars($code); ?>', '<?php echo htmlspecialchars($link['url']); ?>')">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <form method="post" class="d-inline">
                                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                            <input type="hidden" name="delete_suffix" value="<?php echo htmlspecialchars($code); ?>">
                                                            <button type="submit" name="delete_link" class="btn btn-danger btn-sm">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-body">
                        <h3>Create Short Link</h3>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <input type="text" name="url" class="form-control" placeholder="Enter long URL" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="input-group mb-3">
                                        <span class="input-group-text"><?php echo $_SERVER['HTTP_HOST']; ?>/</span>
                                        <input type="text" name="suffix" class="form-control" placeholder="custom-suffix" required>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" name="create_link" class="btn btn-primary w-100">Create</button>
                                </div>
                            </div>
                        </form>

                        <div class="table-responsive mt-4">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Short URL</th>
                                        <th>Original URL</th>
                                        <th>Clicks</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $links = load_data($links_file);
                                    foreach ($links as $code => $link):
                                        if ($link['owner'] === $_SESSION['user']):
                                    ?>
                                    <tr>
                                        <td>
                                            <a href="?code=<?php echo htmlspecialchars($code); ?>" target="_blank">
                                                <?php echo htmlspecialchars($_SERVER['HTTP_HOST'] . '/?code=' . $code); ?>
                                            </a>
                                        </td>
                                        <td><?php echo htmlspecialchars($link['url']); ?></td>
                                        <td><?php echo htmlspecialchars($link['clicks'] ?? 0); ?></td>
                                        <td>
                                            <button class="btn btn-primary btn-sm" onclick="showEditModal('<?php echo htmlspecialchars($code); ?>', '<?php echo htmlspecialchars($link['url']); ?>')">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <input type="hidden" name="delete_suffix" value="<?php echo htmlspecialchars($code); ?>">
                                                <button type="submit" name="delete_link" class="btn btn-danger btn-sm">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php
                                        endif;
                                    endforeach;
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Edit Modal -->
            <div class="modal fade" id="editModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Edit Link</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <form method="post" id="editForm">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="old_suffix" id="oldSuffix">
                                <div class="mb-3">
                                    <label>New Suffix</label>
                                    <input type="text" name="new_suffix" id="newSuffix" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label>New URL</label>
                                    <input type="text" name="new_url" id="newUrl" class="form-control" required>
                                </div>
                                <button type="submit" name="edit_link" class="btn btn-primary">Save Changes</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showEditModal(suffix, url) {
            document.getElementById('oldSuffix').value = suffix;
            document.getElementById('newSuffix').value = suffix;
            document.getElementById('newUrl').value = url;
            new bootstrap.Modal(document.getElementById('editModal')).show();
        }
    </script>
</body>
</html>