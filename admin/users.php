<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

$auth = new Auth();
$auth->requireRole([1]); // Admin only

$database = new Database();
$conn = $database->getConnection();

$message = '';
$message_type = '';

// Handle user actions (unchanged from original)
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
    // ... (other action handlers remain unchanged)
}

// Get filter parameters (unchanged from original)
$role_filter = isset($_GET['role']) ? $_GET['role'] : 'all';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search_term = isset($_GET['search']) ? $_GET['search'] : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Build query conditions (unchanged from original)
$conditions = ["User_Role != 1"];
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

// Sort options (unchanged from original)
$sort_options = [
    'newest' => 'User_CreatedAt DESC',
    'oldest' => 'User_CreatedAt ASC',
    'name_asc' => 'User_Name ASC',
    'name_desc' => 'User_Name DESC',
    'email_asc' => 'User_Email ASC'
];

$order_by = isset($sort_options[$sort_by]) ? $sort_options[$sort_by] : 'User_CreatedAt DESC';

// Get users (unchanged from original)
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

// Get statistics (unchanged from original)
$stats = [];

// Total users (excluding admins)
$query = "SELECT COUNT(*) as total FROM user_accounts WHERE User_Status != 'Deleted' AND User_Role != 1";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Active users (excluding admins)
$query = "SELECT COUNT(*) as total FROM user_accounts WHERE User_Status = 'Active' AND User_Role != 1";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['active_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// New users this month (excluding admins)
$query = "SELECT COUNT(*) as total FROM user_accounts WHERE User_CreatedAt >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND User_Status != 'Deleted' AND User_Role != 1";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['new_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Users by role (excluding admins)
$query = "SELECT User_Role, COUNT(*) as count FROM user_accounts WHERE User_Status != 'Deleted' AND User_Role != 1 GROUP BY User_Role";
$stmt = $conn->prepare($query);
$stmt->execute();
$role_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stats['owners'] = 0;
$stats['renters'] = 0;

foreach($role_counts as $role) {
    switch($role['User_Role']) {
        case 2: $stats['renters'] = $role['count']; break;
        case 3: $stats['owners'] = $role['count']; break;
    }
}

// Role mapping (unchanged from original)
function getRoleName($role_id) {
    switch($role_id) {
        case 1: return 'Admin';
        case 2: return 'Renter';
        case 3: return 'Owner';
        default: return 'Unknown';
    }
}
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
        .dropdown-menu {
            z-index: 2000 !important;
            max-height: 400px;
            overflow-y: auto;
        }
        .card, .card-body {
            overflow: visible !important;
        }
        .user-card {
            border: 1px solid #e9ecef;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
            overflow: visible !important;
            position: relative;
        }
        .user-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .user-avatar {
            width: 35px;
            height: 35px;
            background: #007bff;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.8rem;
            margin-right: 0.5rem;
        }
        .status-badge {
            font-size: 0.75rem;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        .role-badge {
            background: #e3f2fd;
            color: #1976d2;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        .stat-card {
            border-left: 4px solid;
            transition: all 0.3s ease;
            border-radius: 0.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .stat-card.users { border-left-color: #007bff; }
        .stat-card.active { border-left-color: #28a745; }
        .stat-card.new { border-left-color: #ffc107; }
        .stat-card.roles { border-left-color: #dc3545; }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .filter-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border: 1px solid #e9ecef;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .main-content {
            margin-left: 250px;
            padding-top: 70px;
            min-height: 100vh;
            transition: all 0.3s;
            max-width: 100%; /* Prevent excessive stretching */
        }
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            height: 100vh;
            background-color: #343a40;
            transition: all 0.3s;
            z-index: 1000;
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
        .navbar {
            position: fixed;
            top: 0;
            left: 250px;
            right: 0;
            z-index: 1000;
            background-color: #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        @media (max-width: 991px) {
            .sidebar {
                transform: translateX(-250px);
            }
            .main-content {
                margin-left: 0;
                padding-top: 60px;
            }
            .navbar {
                left: 0;
            }
            .sidebar.active {
                transform: translateX(0);
            }
        }
        /* Enhanced fix for action button overlap */
        .user-card .actions-top {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 1001;
        }
        .btn-action {
            white-space: nowrap;
            padding: 0.25rem 1rem;
        }
        .dropdown-menu {
            min-width: 180px;
            left: auto !important;
            right: 0;
            margin-top: 0.25rem;
        }
        .user-card .col.ps-3 {
            max-width: 70%;
            flex: 0 0 70%;
            padding-top: 40px; /* Adjust padding to accommodate top actions */
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
                <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-controls="sidebar">
                    <span class="navbar-toggler-icon"></span>
                </button>
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

                <div class="col-xl-9 col-md-6 mb-4">
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

            <!-- Search/Filter Bar -->
            <form method="GET" class="mb-4">
                <div class="filter-card row g-3 align-items-center">
                    <div class="col-md-3">
                        <label class="form-label mb-1"><i class="fas fa-search me-1"></i>Search Users</label>
                        <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search_term); ?>" placeholder="Search by name or email...">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1"><i class="fas fa-flag me-1"></i>Status</label>
                        <select class="form-select" name="status">
                            <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1"><i class="fas fa-user me-1"></i>Role</label>
                        <select class="form-select" name="role">
                            <option value="all" <?php echo $role_filter == 'all' ? 'selected' : ''; ?>>All Roles</option>
                            <option value="2" <?php echo $role_filter == '2' ? 'selected' : ''; ?>>Renter</option>
                            <option value="3" <?php echo $role_filter == '3' ? 'selected' : ''; ?>>Owner</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1"><i class="fas fa-sort me-1"></i>Sort By</label>
                        <select class="form-select" name="sort">
                            <option value="newest" <?php echo $sort_by == 'newest' ? 'selected' : ''; ?>>Newest First</option>
                            <option value="oldest" <?php echo $sort_by == 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                            <option value="name_asc" <?php echo $sort_by == 'name_asc' ? 'selected' : ''; ?>>Name A-Z</option>
                            <option value="name_desc" <?php echo $sort_by == 'name_desc' ? 'selected' : ''; ?>>Name Z-A</option>
                            <option value="email_asc" <?php echo $sort_by == 'email_asc' ? 'selected' : ''; ?>>Email A-Z</option>
                        </select>
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter"></i> Filter</button>
                    </div>
                </div>
            </form>

            <!-- Users List -->
            <div class="row">
                <div class="col-lg-9">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-users me-2"></i>Users Management
                                <span class="badge bg-primary ms-2"><?php echo count($users); ?> Found</span>
                            </h5>
                            <div>
                                <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
                                    <i class="fas fa-user-plus me-1"></i>Add User
                                </button>
                                <button class="btn btn-warning btn-sm" onclick="bulkSuspendUsers()">
                                    <i class="fas fa-user-slash me-1"></i>Bulk Suspend
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <form id="bulkSuspendForm" method="POST">
                                <?php foreach($users as $user): ?>
                                    <div class="user-card position-relative mb-0" style="border-radius:0; border-width:0 0 1px 0;">
                                        <div class="card-body py-3 px-4">
                                            <div class="actions-top">
                                                <div class="dropdown">
                                                    <button class="btn btn-outline-primary btn-action dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                                        <i class="fas fa-cog"></i> Actions
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <li><a class="dropdown-item" href="#" onclick="viewUserDetails(<?php echo $user['UserID']; ?>)"><i class="fas fa-eye me-2"></i>View Details</a></li>
                                                        <li><a class="dropdown-item" href="#" onclick="editUser(<?php echo $user['UserID']; ?>)"><i class="fas fa-edit me-2"></i>Edit User</a></li>
                                                        <li><form method="POST" style="display:inline;"><input type="hidden" name="user_id" value="<?php echo $user['UserID']; ?>"><input type="hidden" name="new_status" value="Inactive"><button type="submit" name="update_user_status" class="dropdown-item text-warning"><i class="fas fa-ban me-2"></i>Suspend</button></form></li>
                                                        <li><form method="POST" style="display:inline;"><input type="hidden" name="user_id" value="<?php echo $user['UserID']; ?>"><button type="submit" name="delete_user" class="dropdown-item text-danger" onclick="return confirm('Delete this user?')"><i class="fas fa-trash me-2"></i>Delete</button></form></li>
                                                    </ul>
                                                </div>
                                            </div>
                                            <div class="row align-items-center g-0 flex-nowrap">
                                                <div class="col-auto d-flex flex-column align-items-center justify-content-center" style="width: 60px;">
                                                    <input type="checkbox" class="form-check-input bulk-user-checkbox mb-2" name="bulk_user_ids[]" value="<?php echo $user['UserID']; ?>">
                                                    <div class="user-avatar mx-auto">
                                                        <?php echo strtoupper(substr($user['User_Name'], 0, 2)); ?>
                                                    </div>
                                                </div>
                                                <div class="col ps-3">
                                                    <div class="d-flex align-items-center mb-2">
                                                        <span class="badge bg-<?php echo $user['User_Status'] == 'Active' ? 'success' : 'secondary'; ?> status-badge me-2">
                                                            <?php echo $user['User_Status']; ?>
                                                        </span>
                                                        <span class="role-badge me-2"><?php echo $user['User_Role'] == 2 ? 'Renter' : 'Owner'; ?></span>
                                                        <?php if($user['User_IsVerified']): ?>
                                                            <span class="badge bg-success">Verified</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">Unverified</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="fw-bold mb-1" style="font-size:1.1rem;"> <?php echo htmlspecialchars($user['User_Name']); ?> </div>
                                                    <div class="text-muted small mb-1"> <?php echo htmlspecialchars($user['User_Email']); ?> </div>
                                                    <div class="d-flex flex-wrap align-items-center mb-1">
                                                        <div class="me-3"><i class="fas fa-phone me-1"></i> <?php echo htmlspecialchars($user['User_Phone'] ?: 'N/A'); ?></div>
                                                        <div class="me-3"><i class="fas fa-calendar-plus me-1"></i> Joined: <?php echo date('M j, Y', strtotime($user['User_CreatedAt'])); ?></div>
                                                        <div class="me-3">Bookings: <span class="fw-bold"><?php echo $user['total_bookings'] ?? 0; ?></span></div>
                                                        <div class="me-3">Products: <span class="fw-bold"><?php echo $user['total_products'] ?? 0; ?></span></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addUserModal">
                                    <i class="fas fa-user-plus"></i> Add User
                                </button>
                                <button class="btn btn-warning" onclick="bulkSuspendUsers()">
                                    <i class="fas fa-user-slash"></i> Bulk Suspend
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Recent Activities</h5>
                        </div>
                        <div class="card-body">
                            <div class="text-center py-3">
                                <i class="fas fa-clock fa-2x text-muted mb-2"></i>
                                <p class="text-muted mb-0 small">No recent activities</p>
                            </div>
                        </div>
                    </div>
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">System Status</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="small">Users Table</span>
                                <span class="badge bg-success">Ready</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="small">Total Users</span>
                                <strong class="text-success"><?php echo number_format($stats['total_users']); ?></strong>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="small">Active Users</span>
                                <strong class="text-success"><?php echo number_format($stats['active_users']); ?></strong>
                            </div>
                        </div>
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
        function bulkSuspendUsers() {
            if(confirm('Suspend all selected users?')) {
                document.getElementById('bulkSuspendForm').submit();
            }
        }
    </script>
</body>
</html>