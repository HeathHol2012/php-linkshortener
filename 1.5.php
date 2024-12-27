<!--
==============================================
    NOTES & COMMENTS SECTION (yes i had to add this bc i kept breaking the page trying to add coments)
==============================================
    HI i see your using inspect element (or reading my code)
    Please dont change any of the code here on inspect element but feelfree to download and edit the code offline

    
    notes: 
    - version 1.5 coded by heath github user: heathhol2012
    - runns on php so use a local server like ISS or alpache
    - my website will be running this (http://shortify.us.kg/)
    - on server side the usernames and passwords are stored in user.json
    - on server side the links are stored in links.json
    - when upgrading to a newer version of this you can just change the index.php and keep the links.json and user.json
    - if you have any issues with this please make a issue on github and i will try to help!
    -defualt username and password: admin admin (you can change in admin panel)
==============================================

    Changelog:
    v1.5 Changes:
    - Added ability for admin to delete specific links
    - Added link editing functionality for users (their own links) and admin (all links)
    - Added ability for admin to change admin account credentials
-->

<?php
session_start();

// Initialize error message variable
$login_error = '';

// File-based storage since SQL is not allowed
$users_file = 'users.json';
$links_file = 'links.json';

// Initialize storage files if they don't exist
if (!file_exists($users_file)) {
    file_put_contents($users_file, json_encode(['admin' => [
        'password' => 'admin',
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
        $username = $_POST['username'];
        $password = $_POST['password'];
        
        if (!isset($users[$username])) {
            $login_error = "Username not found. Please check your username and try again.";
        } else if ($users[$username]['password'] !== $password) {
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
        $suffix = $_POST['suffix'];
        
        if (!isset($links[$suffix])) {
            $users = load_data($users_file);
            if ($users[$_SESSION['user']]['link_limit'] > 0) {
                $links[$suffix] = [
                    'url' => normalize_url($_POST['url']),
                    'owner' => $_SESSION['user']
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
        $new_admin_username = $_POST['new_admin_username'];
        $new_admin_password = $_POST['new_admin_password'];
        
        // Create new admin entry
        $users[$new_admin_username] = [
            'password' => $new_admin_password,
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
        $suffix_to_delete = $_POST['delete_suffix'];
        
        if ($_SESSION['is_admin'] || 
            ($links[$suffix_to_delete]['owner'] === $_SESSION['user'])) {
            unset($links[$suffix_to_delete]);
            save_data($links_file, $links);
        }
    }

    // Handle link editing
    if (isset($_POST['edit_link'])) {
        $links = load_data($links_file);
        $old_suffix = $_POST['old_suffix'];
        $new_suffix = $_POST['new_suffix'];
        $new_url = $_POST['new_url'];
        
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
        $new_username = $_POST['new_username'];
        if (!isset($users[$new_username])) {
            $users[$new_username] = [
                'password' => $_POST['new_password'],
                'is_admin' => false,
                'link_limit' => (int)$_POST['link_limit']
            ];
            save_data($users_file, $users);
        }
    }
    
    if (isset($_POST['delete_user']) && $_SESSION['is_admin']) {
        $username_to_delete = $_POST['delete_username'];
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
        }
        
        input, button {
            padding: 10px;
            margin: 5px;
            border: none;
            border-radius: 5px;
        }
        
        button {
            background: #4a90e2;
            color: white;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        button:hover {
            background: #357abd;
        }
        
        .link-list {
            display: grid;
            gap: 10px;
        }
        
        .link-item {
            background: rgba(255, 255, 255, 0.05);
            padding: 10px;
            border-radius: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .error {
            color: #ff6b6b;
            margin: 10px 0;
        }
        
        .quota {
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(74, 144, 226, 0.2);
            padding: 10px;
            border-radius: 5px;
        }
        
        .delete-btn {
            background-color: #ff4444;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
            margin-left: 10px;
        }

        .delete-btn:hover {
            background-color: #cc0000;
        }

        .edit-btn {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
            margin-left: 10px;
        }

        .edit-btn:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (!empty($login_error)): ?>
            <div style="color: red; margin: 10px 0; padding: 10px; border: 1px solid red; background-color: #ffe6e6;">
                <?php echo htmlspecialchars($login_error); ?>
            </div>
        <?php endif; ?>

        <?php if (!isset($_SESSION['user'])): ?>
            <div class="card">
                <h2>Login</h2>
                <form method="post">
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
                Links remaining: <?php echo $users[$_SESSION['user']]['link_limit']; ?>
            </div>
            
            <form method="post">
                <button type="submit" name="logout">Logout</button>
            </form>
            
            <?php if ($_SESSION['is_admin']): ?>
                <div class="card">
                    <h2>Admin Panel</h2>

                    <h3>Update Admin Account</h3>
                    <form method="post">
                        <input type="text" name="new_admin_username" placeholder="New Admin Username" required>
                        <input type="password" name="new_admin_password" placeholder="New Admin Password" required>
                        <button type="submit" name="update_admin">Update Admin Credentials</button>
                    </form>

                    <h3>Add User</h3>
                    <form method="post">
                        <input type="text" name="new_username" placeholder="Username" required>
                        <input type="password" name="new_password" placeholder="Password" required>
                        <input type="number" name="link_limit" placeholder="Link Limit" required>
                        <button type="submit" name="add_user">Add User</button>
                    </form>
                    
                    <h3>Users</h3>
                    <?php foreach ($users as $username => $user): ?>
                        <?php if ($username !== $_SESSION['user']): ?>
                            <div class="link-item">
                                <span><?php echo $username; ?> (Links: <?php echo $user['link_limit']; ?>)</span>
                                <form method="post" style="display: inline;" onsubmit="return confirmDelete('<?php echo $username; ?>')">
                                    <input type="hidden" name="delete_username" value="<?php echo $username; ?>">
                                    <button type="submit" name="delete_user" class="delete-btn">Delete User</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    
                    <h3>All Links</h3>
                    <?php foreach ($links as $suffix => $link): ?>
                        <div class="link-item">
                            <span><?php echo $_SERVER['HTTP_HOST']; ?>/<?php echo $suffix; ?> → <?php echo $link['url']; ?> (Owner: <?php echo $link['owner']; ?>)</span>
                            <div>
                                <button onclick="showEditForm('<?php echo $suffix; ?>', '<?php echo $link['url']; ?>')" class="edit-btn">Edit</button>
                                <form method="post" style="display: inline;" onsubmit="return confirmDeleteLink('<?php echo $suffix; ?>')">
                                    <input type="hidden" name="delete_suffix" value="<?php echo $suffix; ?>">
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
                        <input type="text" name="url" placeholder="Long URL" required>
                        <div style="display: flex; align-items: center;">
                            <span><?php echo $_SERVER['HTTP_HOST']; ?>/</span>
                            <input type="text" name="suffix" placeholder="custom-suffix" required>
                        </div>
                        <button type="submit" name="create_link">Create Link</button>
                    </form>
                    
                    <h3>Your Links</h3>
                    <?php foreach ($links as $suffix => $link): ?>
                        <?php if ($link['owner'] === $_SESSION['user']): ?>
                            <div class="link-item">
                                <span><?php echo $_SERVER['HTTP_HOST']; ?>/<?php echo $suffix; ?> → <?php echo $link['url']; ?></span>
                                <div>
                                    <button onclick="showEditForm('<?php echo $suffix; ?>', '<?php echo $link['url']; ?>')" class="edit-btn">Edit</button>
                                    <form method="post" style="display: inline;" onsubmit="return confirmDeleteLink('<?php echo $suffix; ?>')">
                                        <input type="hidden" name="delete_suffix" value="<?php echo $suffix; ?>">
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
    <div id="editModal" style="display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #333; padding: 20px; border-radius: 10px; z-index: 1000;">
        <form method="post" id="editForm">
            <input type="hidden" name="old_suffix" id="oldSuffix">
            <input type="text" name="new_suffix" id="newSuffix" placeholder="New Suffix" required>
            <input type="text" name="new_url" id="newUrl" placeholder="New URL" required>
            <button type="submit" name="edit_link">Save Changes</button>
            <button type="button" onclick="hideEditForm()">Cancel</button>
        </form>
    </div>

    <script>
        function confirmDelete(username) {
            return confirm('Are you sure you want to delete user "' + username + '"?');
        }

        function confirmDeleteLink(suffix) {
            return confirm('Are you sure you want to delete the link with suffix "' + suffix + '"?');
        }

        function showEditForm(suffix, url) {
            document.getElementById('editModal').style.display = 'block';
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
