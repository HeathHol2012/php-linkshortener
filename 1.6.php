<!--
==============================================
    NOTES & COMMENTS SECTION 
==============================================
    HI i see your using inspect element (or reading my code)
    Please dont change any of the code here on inspect element but feelfree to download and edit the code offline

    
    notes: 
    - version 1.6 coded by heath github user: heathhol2012
    - runns on php so use a local server like ISS or alpache
    - my website will be running this (http://shortify.us.kg/)
    - on server side the usernames and passwords are stored in user.json
    - on server side the links are stored in links.json
    - when upgrading to a newer version of this you can just change the index.php and keep the links.json and user.json
    - if you have any issues with this please make a issue on github and i will try to help!
    -defualt username and password: admin admin (you can change in admin panel)
==============================================

    Changelog:
    v1.6 Changes:
    - Added password hashing for improved security
    - Added XSS protection by escaping output
    - Added CSRF token protection for forms
    - Improved UI styling and responsiveness
-->

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
    // If URL doesn't start with a protocol, prepend http://
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
                    'owner' => htmlspecialchars($_SESSION['user'])
                ];
                $users[$_SESSION['user']]['link_limit']--;
                save_data($links_file, $links);
                save_data($users_file, $users);
            }
        }
    }

    // Handle admin credential update
    if (isset($_POST['update_admin']) && $_SESSION['is_admin']) {
        $users = load_data($users_file);
        $new_admin_username = htmlspecialchars($_POST['new_admin_username']);
        $new_admin_password = $_POST['new_admin_password'];
        
        // Create new admin entry with hashed password
        $users[$new_admin_username] = [
            'password' => password_hash($new_admin_password, PASSWORD_DEFAULT),
            'is_admin' => true,
            'link_limit' => PHP_INT_MAX
        ];
        
        // Remove old admin if username changed
        if ($new_admin_username !== 'admin') {
            unset($users['admin']);
        }
        
        save_data($users_file, $users);
        $_SESSION['user'] = $new_admin_username;
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    // Handle link deletion
    if (isset($_POST['delete_link'])) {
        $links = load_data($links_file);
        $suffix_to_delete = htmlspecialchars($_POST['delete_suffix']);
        
        if ($_SESSION['is_admin'] || 
            ($links[$suffix_to_delete]['owner'] === $_SESSION['user'])) {
            unset($links[$suffix_to_delete]);
            save_data($links_file, $links);
        }
    }

    // Handle link editing
    if (isset($_POST['edit_link'])) {
        $links = load_data($links_file);
        $old_suffix = htmlspecialchars($_POST['old_suffix']);
        $new_suffix = htmlspecialchars($_POST['new_suffix']);
        $new_url = htmlspecialchars($_POST['new_url']);
        
        if ($_SESSION['is_admin'] || 
            ($links[$old_suffix]['owner'] === $_SESSION['user'])) {
            
            $link_data = $links[$old_suffix];
            $link_data['url'] = normalize_url($new_url);
            
            // If suffix changed, remove old and add new
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

        // Delete user's links
        foreach ($links as $suffix => $link) {
            if ($link['owner'] === $username_to_delete) {
                unset($links[$suffix]);
            }
        }
        
        // Delete user
        unset($users[$username_to_delete]);
        
        // Save changes
        save_data($users_file, $users);
        save_data($links_file, $links);
        
        // Refresh page
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Handle link redirects
$request_uri = $_SERVER['REQUEST_URI'];
if (preg_match('/\/([^\/]+)$/', $request_uri, $matches)) {
    $suffix = $matches[1];
    $links = load_data($links_file);
    if (isset($links[$suffix])) {
        header('Location: ' . $links[$suffix]['url']);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Link Shortener</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 30px;
            min-height: 100vh;
            background: linear-gradient(135deg, #1a1a1a, #2a2a2a);
            color: #fff;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .card {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        input, button {
            padding: 12px;
            margin: 5px;
            border: none;
            border-radius: 5px;
            font-size: 14px;
        }
        
        input {
            background: rgba(255, 255, 255, 0.9);
            width: calc(100% - 24px);
            transition: all 0.3s ease;
        }
        
        input:focus {
            outline: none;
            box-shadow: 0 0 0 2px #4a90e2;
        }
        
        button {
            background: #4a90e2;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            font-weight: bold;
            letter-spacing: 1px;
        }
        
        button:hover {
            background: #357abd;
            transform: translateY(-2px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        .link-list {
            display: grid;
            gap: 10px;
        }
        
        .link-item {
            background: rgba(255, 255, 255, 0.05);
            padding: 15px;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }
        
        .link-item:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(5px);
        }
        
        .error {
            color: #ff6b6b;
            margin: 10px 0;
            padding: 10px;
            border-radius: 5px;
            background: rgba(255, 107, 107, 0.1);
        }
        
        .quota {
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(74, 144, 226, 0.2);
            padding: 12px 20px;
            border-radius: 25px;
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            font-weight: bold;
        }
        
        .delete-btn {
            background-color: #ff4444;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            margin-left: 10px;
            font-size: 12px;
        }

        .delete-btn:hover {
            background-color: #cc0000;
        }

        .edit-btn {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            margin-left: 10px;
            font-size: 12px;
        }

        .edit-btn:hover {
            background-color: #45a049;
        }
        
        h2, h3 {
            color: #4a90e2;
            margin-bottom: 20px;
        }
        
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        #editModal {
            background: #333;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            width: 90%;
            max-width: 500px;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (!empty($login_error)): ?>
            <div class="error">
                <?php echo htmlspecialchars($login_error); ?>
            </div>
        <?php endif; ?>

        <?php if (!isset($_SESSION['user'])): ?>
            <div class="card">
                <h2>Login</h2>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="text" name="username" placeholder="Username" required>
                    <input type="password" name="password" placeholder="Password" required>
                    <button type="submit" name="login">Login</button>
                </form>
            </div>
        <?php else: ?>
            <?php
            $users = load_data($users_file);
            $links = load_data($links_file);
            ?>
            
            <div class="quota">
                Links remaining: <?php echo htmlspecialchars($users[$_SESSION['user']]['link_limit']); ?>
            </div>
            
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <button type="submit" name="logout">Logout</button>
            </form>
            
            <?php if ($_SESSION['is_admin']): ?>
                <div class="card">
                    <h2>Admin Panel</h2>

                    <h3>Update Admin Account</h3>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="text" name="new_admin_username" placeholder="New Admin Username" required>
                        <input type="password" name="new_admin_password" placeholder="New Admin Password" required>
                        <button type="submit" name="update_admin">Update Admin Credentials</button>
                    </form>

                    <h3>Add User</h3>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="text" name="new_username" placeholder="Username" required>
                        <input type="password" name="new_password" placeholder="Password" required>
                        <input type="number" name="link_limit" placeholder="Link Limit" required>
                        <button type="submit" name="add_user">Add User</button>
                    </form>
                    
                    <h3>Users</h3>
                    <?php foreach ($users as $username => $user): ?>
                        <?php if ($username !== $_SESSION['user']): ?>
                            <div class="link-item">
                                <span><?php echo htmlspecialchars($username); ?> (Links: <?php echo htmlspecialchars($user['link_limit']); ?>)</span>
                                <form method="post" style="display: inline;" onsubmit="return confirmDelete('<?php echo htmlspecialchars($username); ?>')">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="delete_username" value="<?php echo htmlspecialchars($username); ?>">
                                    <button type="submit" name="delete_user" class="delete-btn">Delete User</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    
                    <h3>All Links</h3>
                    <?php foreach ($links as $suffix => $link): ?>
                        <div class="link-item">
                            <span><?php echo htmlspecialchars($_SERVER['HTTP_HOST']); ?>/<?php echo htmlspecialchars($suffix); ?> → <?php echo htmlspecialchars($link['url']); ?> (Owner: <?php echo htmlspecialchars($link['owner']); ?>)</span>
                            <div>
                                <button onclick="showEditForm('<?php echo htmlspecialchars($suffix); ?>', '<?php echo htmlspecialchars($link['url']); ?>')" class="edit-btn">Edit</button>
                                <form method="post" style="display: inline;" onsubmit="return confirmDeleteLink('<?php echo htmlspecialchars($suffix); ?>')">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="delete_suffix" value="<?php echo htmlspecialchars($suffix); ?>">
                                    <button type="submit" name="delete_link" class="delete-btn">Delete</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="card">
                    <h2>Create Short Link</h2>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="text" name="url" placeholder="Long URL" required>
                        <div style="display: flex; align-items: center;">
                            <span><?php echo htmlspecialchars($_SERVER['HTTP_HOST']); ?>/</span>
                            <input type="text" name="suffix" placeholder="custom-suffix" required>
                        </div>
                        <button type="submit" name="create_link">Create Link</button>
                    </form>
                    
                    <h3>Your Links</h3>
                    <?php foreach ($links as $suffix => $link): ?>
                        <?php if ($link['owner'] === $_SESSION['user']): ?>
                            <div class="link-item">
                                <span><?php echo htmlspecialchars($_SERVER['HTTP_HOST']); ?>/<?php echo htmlspecialchars($suffix); ?> → <?php echo htmlspecialchars($link['url']); ?></span>
                                <div>
                                    <button onclick="showEditForm('<?php echo htmlspecialchars($suffix); ?>', '<?php echo htmlspecialchars($link['url']); ?>')" class="edit-btn">Edit</button>
                                    <form method="post" style="display: inline;" onsubmit="return confirmDeleteLink('<?php echo htmlspecialchars($suffix); ?>')">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="delete_suffix" value="<?php echo htmlspecialchars($suffix); ?>">
                                        <button type="submit" name="delete_link" class="delete-btn">Delete</button>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Edit Link Modal -->
    <div id="editModal" style="display: none;" class="modal-overlay">
        <div class="card">
            <form method="post" id="editForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="old_suffix" id="oldSuffix">
                <input type="text" name="new_suffix" id="newSuffix" placeholder="New Suffix" required>
                <input type="text" name="new_url" id="newUrl" placeholder="New URL" required>
                <button type="submit" name="edit_link">Save Changes</button>
                <button type="button" onclick="hideEditForm()" style="background: #666;">Cancel</button>
            </form>
        </div>
    </div>

    <script>
        function confirmDelete(username) {
            return confirm('Are you sure you want to delete user "' + username + '"?');
        }

        function confirmDeleteLink(suffix) {
            return confirm('Are you sure you want to delete the link with suffix "' + suffix + '"?');
        }

        function showEditForm(suffix, url) {
            document.getElementById('editModal').style.display = 'flex';
            document.getElementById('oldSuffix').value = suffix;
            document.getElementById('newSuffix').value = suffix;
            document.getElementById('newUrl').value = url;
        }

        function hideEditForm() {
            document.getElementById('editModal').style.display = 'none';
        }
    </script>
</body>
</html>




