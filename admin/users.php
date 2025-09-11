<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

$auth = new Auth();
$auth->requireRole([1]); // Admin only

$database = new Database();
$conn = $database->getConnection();

$message = '';
$message_type = '';

// Handle user actions
if ($_POST) {
    if (isset($_POST['update_user_status'])) {
        $user_id = $_POST['user_id'];
        $new_status = $_POST['new_status'];
        
        $query = "UPDATE user_accounts SET User_Status = ? WHERE UserID = ?";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(1, $new_status);
        $stmt->bindParam(2, $user_id);
        
        if ($stmt->execute()) {
            $message = "User status updated successfully!";
            $message_type = "success";
        } else {
            $message = "Failed to update user status.";
            $message_type = "danger";
        }
    }
    
    if (isset($_POST['update_user_role'])) {
        $user_id = $_POST['user_id'];
        $new_role = $_POST['new_role'];
        
        $query = "UPDATE user_accounts SET User_Role = ? WHERE UserID = ?";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(1, $new_role);
        $stmt->bindParam(2, $user_id);
        
        if ($stmt->execute()) {
            $message = "User role updated successfully!";
            $message_type = "success";
        } else {
            $message = "Failed to update user role.";
            $message_type = "danger";
        }
    }
    
    if (isset($_POST['delete_user'])) {
        $user_id = $_POST['user_id'];
        
        // Don't allow deletion of admin users
        $query = "SELECT User_Role FROM user_accounts WHERE UserID = ?";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(1, $user_id);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && $user['User_Role'] != 1) {
            $query = "UPDATE user_accounts SET User_Status = 'Deleted' WHERE UserID = ?";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(1, $user_id);
            
            if ($stmt->execute()) {
                $message = "User marked as deleted successfully!";
                $message_type = "success";
            } else {
                $message = "Failed to delete user.";
                $message_type = "danger";
            }
        } else {
            $message = "Cannot delete admin users.";
            $message_type = "warning";
        }
    }
    
    if (isset($_POST['create_user'])) {
        $user_name = trim($_POST['user_name']);
        $user_email = trim($_POST['user_email']);
        $user_password = password_hash($_POST['user_password'], PASSWORD_DEFAULT);
        $user_phone = trim($_POST['user_phone']);
        $user_role = $_POST['user_role'];
        $user_gender = $_POST['user_gender'];
        $user_birthdate = !empty($_POST['user_birthdate']) ? $_POST['user_birthdate'] : null;
        $user_status = 'Active';
        $user_is_verified = 0; // Default to not verified
        
        try {
            // Check if email already exists
            $check_query = "SELECT UserID FROM user_accounts WHERE User_Email = ?";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bindParam(1, $user_email);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() > 0) {
                $message = "Email address already exists!";
                $message_type = "danger";
            } else {
                $query = "INSERT INTO user_accounts (User_Name, User_Email, User_Password, User_Phone, User_Role, User_CreatedAt, User_UpdatedAt, User_IsVerified, User_Status, User_Birthdate, User_Gender) VALUES (?, ?, ?, ?, ?, NOW(), NOW(), ?, ?, ?, ?)";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(1, $user_name);
                $stmt->bindParam(2, $user_email);
                $stmt->bindParam(3, $user_password);
                $stmt->bindParam(4, $user_phone);
                $stmt->bindParam(5, $user_role);
                $stmt->bindParam(6, $user_is_verified);
                $stmt->bindParam(7, $user_status);
                $stmt->bindParam(8, $user_birthdate);
                $stmt->bindParam(9, $user_gender);
                
                if ($stmt->execute()) {
                    $message = "User created successfully!";
                    $message_type = "success";
                } else {
                    $message = "Failed to create user.";
                    $message_type = "danger";
                }
            }
        } catch (PDOException $e) {
            $message = "Error creating user: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

// Get filter parameters
$role_filter = isset($_GET['role']) ? $_GET['role'] : 'all';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search_term = isset($_GET['search']) ? $_GET['search'] : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Build query conditions
$conditions = ["1=1"];
$params = [];

if ($role_filter && $role_filter != 'all') {
    $conditions[] = "User_Role = ?";
    $params[] = $role_filter;
}

if ($status_filter && $status_filter != 'all') {
    $conditions[] = "User_Status = ?";
    $params[] = ucfirst($status_filter);
}

if ($search_term) {
    $conditions[] = "(User_Name LIKE ? OR User_Email LIKE ?)";
    $params[] = "%$search_term%";
    $params[] = "%$search_term%";
}

// Sort options
$sort_options = [
    'newest' => 'User_CreatedAt DESC',
    'oldest' => 'User_CreatedAt ASC',
    'name_asc' => 'User_Name ASC',
    'name_desc' => 'User_Name DESC',
    'email_asc' => 'User_Email ASC'
];

$order_by = isset($sort_options[$sort_by]) ? $sort_options[$sort_by] : 'User_CreatedAt DESC';

// Get users
$query = "SELECT u.*, 
          (SELECT COUNT(*) FROM bookings WHERE RenterID = u.UserID) as total_bookings,
          (SELECT COUNT(*) FROM products WHERE OwnerID = u.UserID) as total_products,
          (SELECT SUM(Book_TotalAmount) FROM bookings WHERE RenterID = u.UserID AND Book_Status IN ('Active', 'Completed')) as total_spent
          FROM user_accounts u 
          WHERE " . implode(' AND ', $conditions) . " 
          ORDER BY " . $order_by;

$stmt = $conn->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats = [];

// Total users
$query = "SELECT COUNT(*) as total FROM user_accounts WHERE User_Status != 'Deleted'";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Active users
$query = "SELECT COUNT(*) as total FROM user_accounts WHERE User_Status = 'Active'";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['active_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// New users this month
$query = "SELECT COUNT(*) as total FROM user_accounts WHERE User_CreatedAt >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND User_Status != 'Deleted'";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['new_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Users by role
$query = "SELECT User_Role, COUNT(*) as count FROM user_accounts WHERE User_Status != 'Deleted' GROUP BY User_Role";
$stmt = $conn->prepare($query);
$stmt->execute();
$role_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stats['admins'] = 0;
$stats['owners'] = 0;
$stats['renters'] = 0;

foreach($role_counts as $role) {
    switch($role['User_Role']) {
        case 1: $stats['admins'] = $role['count']; break;
        case 2: $stats['renters'] = $role['count']; break;
        case 3: $stats['owners'] = $role['count']; break;
    }
}

// Role mapping (corrected to match your schema: 1=Admin, 2=Renter, 3=Owner)
function getRoleName($role_id) {
    switch($role_id) {
        case 1: return 'Admin';
        case 2: return 'Renter';
        case 3: return 'Owner';
        default: return 'Unknown';
    }
}

// Recent user activities
$query = "SELECT 'User Registration' as activity, User_Name as details, User_CreatedAt as created_at 
          FROM user_accounts 
          WHERE User_Status != 'Deleted'
          ORDER BY User_CreatedAt DESC 
          LIMIT 5";
$stmt = $conn->prepare($query);
$stmt->execute();
$recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - RentHub PH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background-color: #343a40;
        }
        .sidebar .nav-link {
            color: #adb5bd;
            padding: 0.75rem 1rem;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: #fff;
            background-color: #495057;
        }
        .main-content {
            margin-left: 250px;
        }
        .stat-card {
            border-left: 4px solid;
        }
        .stat-card.users { border-left-color: #007bff; }
        .stat-card.active { border-left-color: #28a745; }
        .stat-card.new { border-left-color: #ffc107; }
        .stat-card.roles { border-left-color: #dc3545; }
        
        .user-card {
            transition: all 0.3s ease;
        }
        .user-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .user-avatar {
            width: 40px;
            height: 40px;
            background: #007bff;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        .role-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            background-color: #e9ecef;
            color: #495057;
            border-radius: 0.25rem;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar position-fixed top-0 start-0" style="width: 250px; z-index: 1000;">
        <div class="p-3">
            <h4 class="text-white">
                <i class="fas fa-home"></i> RentHub PH
            </h4>
            <p class="text-muted small">Admin Panel</p>
        </div>
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="users.php">
                    <i class="fas fa-users"></i> Users Management
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="products.php">
                    <i class="fas fa-box"></i> Products Management
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="bookings.php">
                    <i class="fas fa-calendar-check"></i> Bookings Management
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="categories.php">
                    <i class="fas fa-tags"></i> Categories
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="subscriptions.php">
                    <i class="fas fa-credit-card"></i> Subscriptions
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="payments.php">
                    <i class="fas fa-money-bill"></i> Payments
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="reports.php">
                    <i class="fas fa-chart-bar"></i> Reports
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="settings.php">
                    <i class="fas fa-cog"></i> Settings
                </a>
            </li>
            <li class="nav-item mt-3">
                <a class="nav-link" href="../index.php">
                    <i class="fas fa-arrow-left"></i> Back to Site
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </li>
        </ul>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navigation -->
        <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
            <div class="container-fluid">
                <h5 class="mb-0">User Management</h5>
                <div class="navbar-nav ms-auto">
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> <?php echo $_SESSION['user_name']; ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                            <li><a class="dropdown-item" href="../logout.php">Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Dashboard Content -->
        <div class="container-fluid p-4">
            <?php if($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
                <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card users">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        Total Users
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold"><?php echo number_format($stats['total_users']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-users fa-2x text-primary"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card active">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        Active Users
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold"><?php echo number_format($stats['active_users']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-user-check fa-2x text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card new">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                        New This Month
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold"><?php echo number_format($stats['new_users']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-user-plus fa-2x text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card roles">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                        User Roles
                                    </div>
                                    <div class="small">
                                        Admin: <?php echo $stats['admins']; ?> | 
                                        Owner: <?php echo $stats['owners']; ?> | 
                                        Renter: <?php echo $stats['renters']; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-user-tag fa-2x text-danger"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activities and Quick Actions -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Recent Activities</h5>
                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#userListModal">
                                <i class="fas fa-users"></i> View All Users
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <?php foreach($recent_activities as $activity): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo htmlspecialchars($activity['activity']); ?>:</strong>
                                        <?php echo htmlspecialchars($activity['details']); ?>
                                    </div>
                                    <small class="text-muted"><?php echo date('M j, Y', strtotime($activity['created_at'])); ?></small>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userListModal">
                                    <i class="fas fa-users"></i> Manage Users
                                </button>
                                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addUserModal">
                                    <i class="fas fa-user-plus"></i> Add User
                                </button>
                                <a href="reports.php" class="btn btn-info">
                                    <i class="fas fa-download"></i> Generate Report
                                </a>
                                <a href="settings.php" class="btn btn-warning">
                                    <i class="fas fa-cog"></i> System Settings
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">System Status</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>Server Status</span>
                                <span class="badge bg-success">Online</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>Database</span>
                                <span class="badge bg-success">Connected</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span>Last Backup</span>
                                <span class="text-muted small">2 hours ago</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- User List Modal -->
    <div class="modal fade" id="userListModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">User Management</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Search and Filters -->
                    <div class="mb-4 p-3 bg-light rounded">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <input type="text" class="form-control" name="search" 
                                       value="<?php echo htmlspecialchars($search_term); ?>" 
                                       placeholder="Search by name or email...">
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" name="role">
                                    <option value="all" <?php echo $role_filter == 'all' ? 'selected' : ''; ?>>All Roles</option>
                                    <option value="1" <?php echo $role_filter == '1' ? 'selected' : ''; ?>>Admin</option>
                                    <option value="2" <?php echo $role_filter == '2' ? 'selected' : ''; ?>>Renter</option>
                                    <option value="3" <?php echo $role_filter == '3' ? 'selected' : ''; ?>>Owner</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" name="status">
                                    <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                                    <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" name="sort">
                                    <option value="newest" <?php echo $sort_by == 'newest' ? 'selected' : ''; ?>>Newest</option>
                                    <option value="name_asc" <?php echo $sort_by == 'name_asc' ? 'selected' : ''; ?>>Name A-Z</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">Filter</button>
                            </div>
                        </form>
                    </div>

                    <!-- Users List -->
                    <div style="max-height: 500px; overflow-y: auto;">
                        <?php foreach($users as $user): ?>
                        <div class="card user-card mb-3">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="user-avatar me-3">
                                        <?php echo strtoupper(substr($user['User_Name'], 0, 2)); ?>
                                    </div>
                                    <div class="flex-fill">
                                        <div class="d-flex align-items-center mb-1">
                                            <span class="role-badge me-2"><?php echo getRoleName($user['User_Role']); ?></span>
                                            <span class="badge bg-<?php echo $user['User_Status'] == 'Active' ? 'success' : 'warning'; ?> status-badge">
                                                <?php echo $user['User_Status']; ?>
                                            </span>
                                        </div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($user['User_Name']); ?></h6>
                                        <p class="text-muted small mb-0"><?php echo htmlspecialchars($user['User_Email']); ?></p>
                                    </div>
                                    <div class="text-end me-3">
                                        <small class="text-muted">ID: #<?php echo str_pad($user['UserID'], 6, '0', STR_PAD_LEFT); ?></small><br>
                                        <small class="text-muted">Joined: <?php echo date('M j, Y', strtotime($user['User_CreatedAt'])); ?></small>
                                    </div>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                            Actions
                                        </button>
                                        <ul class="dropdown-menu">
                                            <?php if($user['User_Status'] == 'Active'): ?>
                                                <li>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['UserID']; ?>">
                                                        <input type="hidden" name="new_status" value="Inactive">
                                                        <button type="submit" name="update_user_status" class="dropdown-item">Suspend</button>
                                                    </form>
                                                </li>
                                            <?php else: ?>
                                                <li>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['UserID']; ?>">
                                                        <input type="hidden" name="new_status" value="Active">
                                                        <button type="submit" name="update_user_status" class="dropdown-item">Activate</button>
                                                    </form>
                                                </li>
                                            <?php endif; ?>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item" href="#" onclick="changeRole(<?php echo $user['UserID']; ?>, 1)">Make Admin</a></li>
                                            <li><a class="dropdown-item" href="#" onclick="changeRole(<?php echo $user['UserID']; ?>, 2)">Make Renter</a></li>
                                            <li><a class="dropdown-item" href="#" onclick="changeRole(<?php echo $user['UserID']; ?>, 3)">Make Owner</a></li>
                                            <?php if($user['User_Role'] != 1): ?>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['UserID']; ?>">
                                                    <button type="submit" name="delete_user" class="dropdown-item text-danger"
                                                            onclick="return confirm('Delete this user?')">Delete</button>
                                                </form>
                                            </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-plus me-2"></i>Add New User
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="user_name" required placeholder="Enter full name">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email Address <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" name="user_email" required placeholder="Enter email address">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" name="user_password" required placeholder="Enter password">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" name="user_phone" placeholder="Enter phone number">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">User Role <span class="text-danger">*</span></label>
                                <select class="form-select" name="user_role" required>
                                    <option value="">Select Role</option>
                                    <option value="1">Admin</option>
                                    <option value="2">Renter</option>
                                    <option value="3">Owner</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Gender</label>
                                <select class="form-select" name="user_gender">
                                    <option value="">Select Gender</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Birth Date</label>
                                <input type="date" class="form-control" name="user_birthdate">
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>User Account Guidelines:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Email must be unique in the system</li>
                                <li>Password will be securely hashed</li>
                                <li>User will be created as Active by default</li>
                                <li>User verification status will be set to unverified</li>
                            </ul>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_user" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Create User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Change user role function
        function changeRole(userId, newRole) {
            if (confirm('Are you sure you want to change this user\'s role?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="user_id" value="${userId}">
                    <input type="hidden" name="new_role" value="${newRole}">
                    <input type="hidden" name="update_user_role" value="1">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Auto-hide alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                if (alert.classList.contains('alert-success')) {
                    alert.style.transition = 'opacity 0.5s ease';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                }
            });
        }, 5000);
    </script>
</body>
</html>