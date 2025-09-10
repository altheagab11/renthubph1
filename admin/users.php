<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

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
$stats['both'] = 0;

foreach($role_counts as $role) {
    switch($role['User_Role']) {
        case 1: $stats['admins'] = $role['count']; break;
        case 2: $stats['renters'] = $role['count']; break;
        case 3: $stats['both'] = $role['count']; break;
        case 4: $stats['owners'] = $role['count']; break;
    }
}

// Role mapping
function getRoleName($role_id) {
    switch($role_id) {
        case 1: return 'Admin';
        case 2: return 'Renter';
        case 3: return 'Both';
        case 4: return 'Owner';
        default: return 'Unknown';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - RentHub PH Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            --secondary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --accent-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --info-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --warning-gradient: linear-gradient(135deg, #f6d365 0%, #fda085 100%);
            --admin-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --sidebar-width: 250px;
        }
        
        .sidebar {
            min-height: 100vh;
            background: var(--admin-gradient);
            width: var(--sidebar-width);
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
            transition: all 0.3s ease;
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            margin-bottom: 0.25rem;
            transition: all 0.3s ease;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: #fff;
            background-color: rgba(255,255,255,0.2);
            transform: translateX(5px);
        }
        
        .main-content {
            margin-left: var(--sidebar-width);
            transition: margin-left 0.3s ease;
        }
        
        .admin-header {
            background: var(--admin-gradient);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 0 0 20px 20px;
        }
        
        .stat-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
            transition: all 0.3s ease;
            overflow: hidden;
            margin-bottom: 1.5rem;
        }
        
        .stat-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        
        .stat-card.total { background: var(--primary-gradient); color: white; }
        .stat-card.active { background: var(--info-gradient); color: white; }
        .stat-card.new { background: var(--warning-gradient); color: white; }
        .stat-card.roles { background: var(--accent-gradient); color: white; }
        
        .user-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            overflow: hidden;
            position: relative;
            margin-bottom: 2rem;
            border-left: 4px solid #667eea;
        }
        
        .user-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }
        
        .user-card.admin { border-left-color: #667eea; }
        .user-card.renter { border-left-color: #4facfe; }
        .user-card.owner { border-left-color: #11998e; }
        .user-card.both { border-left-color: #f093fb; }
        
        .user-status {
            position: absolute;
            top: 15px;
            right: 15px;
            z-index: 2;
        }
        
        .status-badge {
            border-radius: 20px;
            padding: 0.5rem 1rem;
            font-size: 0.75rem;
            font-weight: 600;
            color: white;
        }
        
        .status-badge.active { background: #28a745; }
        .status-badge.inactive { background: #6c757d; }
        .status-badge.suspended { background: #dc3545; }
        .status-badge.pending { background: #ffc107; color: #000; }
        
        .search-filters {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .user-info {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 15px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .user-stats {
            background: rgba(102, 126, 234, 0.1);
            border-radius: 15px;
            padding: 1rem;
            border-left: 4px solid #667eea;
            margin: 1rem 0;
        }
        
        .action-btn {
            border-radius: 20px;
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            margin: 0.25rem;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .action-btn.edit { background: var(--info-gradient); color: white; }
        .action-btn.suspend { background: var(--accent-gradient); color: white; }
        .action-btn.delete { background: var(--warning-gradient); color: white; }
        .action-btn.activate { background: var(--primary-gradient); color: white; }
        
        .navbar {
            border-bottom: 1px solid #e9ecef;
            background: rgba(255,255,255,0.95) !important;
            backdrop-filter: blur(10px);
        }
        
        .card {
            border-radius: 15px;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .form-control, .form-select {
            border-radius: 15px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 0;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 1rem;
        }
        
        .user-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--admin-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            margin-right: 1rem;
            flex-shrink: 0;
        }
        
        .role-badge {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
            border-radius: 10px;
            padding: 0.25rem 0.75rem;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            display: inline-block;
        }
        
        .role-badge.admin { background: rgba(102, 126, 234, 0.1); color: #667eea; }
        .role-badge.renter { background: rgba(79, 172, 254, 0.1); color: #4facfe; }
        .role-badge.owner { background: rgba(17, 153, 142, 0.1); color: #11998e; }
        .role-badge.both { background: rgba(240, 147, 251, 0.1); color: #f093fb; }
        
        .quick-actions {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }
        
        .bulk-actions {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border-left: 4px solid #667eea;
        }
        
        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            .sidebar {
                margin-left: calc(-1 * var(--sidebar-width));
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .sidebar.show {
                margin-left: 0;
            }
            
            .search-filters, .quick-actions {
                padding: 1rem;
            }
            
            .user-card {
                margin-bottom: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="p-3">
            <h4 class="text-white mb-1">
                <i class="fas fa-shield-alt"></i> RentHub PH
            </h4>
            <p class="text-white-50 small mb-0">Admin Panel</p>
        </div>
        
        <div class="px-3 pb-3">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="users.php">
                        <i class="fas fa-users me-2"></i> User Management
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="products.php">
                        <i class="fas fa-box me-2"></i> Product Management
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="bookings.php">
                        <i class="fas fa-calendar-check me-2"></i> Booking Management
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="payments.php">
                        <i class="fas fa-credit-card me-2"></i> Payment Management
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="reports.php">
                        <i class="fas fa-chart-bar me-2"></i> Reports & Analytics
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="settings.php">
                        <i class="fas fa-cog me-2"></i> System Settings
                    </a>
                </li>
                <li class="nav-item mt-3">
                    <hr class="text-white-50">
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../index.php">
                        <i class="fas fa-arrow-left me-2"></i> Back to Site
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../logout.php">
                        <i class="fas fa-sign-out-alt me-2"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Admin Header -->
        <div class="admin-header">
            <div class="container-fluid">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h2 class="mb-2">
                            <i class="fas fa-users me-3"></i>User Management
                        </h2>
                        <p class="mb-0 opacity-90">Manage and monitor all platform users</p>
                    </div>
                    <div class="col-md-6 text-end">
                        <button class="btn btn-outline-light me-2" data-bs-toggle="modal" data-bs-target="#addUserModal">
                            <i class="fas fa-user-plus me-2"></i>Add New User
                        </button>
                        <button class="btn btn-light">
                            <i class="fas fa-download me-2"></i>Export Users
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="container-fluid px-4">
            <?php if($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert" style="border-radius: 15px;">
                <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : ($message_type == 'danger' ? 'exclamation-triangle' : 'info-circle'); ?> me-2"></i>
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card total">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1 opacity-75">
                                        Total Users
                                    </div>
                                    <div class="h4 mb-0 font-weight-bold"><?php echo number_format($stats['total_users']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-users fa-2x opacity-75"></i>
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
                                    <div class="text-xs font-weight-bold text-uppercase mb-1 opacity-75">
                                        Active Users
                                    </div>
                                    <div class="h4 mb-0 font-weight-bold"><?php echo number_format($stats['active_users']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-user-check fa-2x opacity-75"></i>
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
                                    <div class="text-xs font-weight-bold text-uppercase mb-1 opacity-75">
                                        New This Month
                                    </div>
                                    <div class="h4 mb-0 font-weight-bold"><?php echo number_format($stats['new_users']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-user-plus fa-2x opacity-75"></i>
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
                                    <div class="text-xs font-weight-bold text-uppercase mb-1 opacity-75">
                                        User Roles
                                    </div>
                                    <div class="small">
                                        Admins: <?php echo $stats['admins']; ?> | 
                                        Owners: <?php echo $stats['owners']; ?> | 
                                        Renters: <?php echo $stats['renters']; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-user-tag fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <h5 class="text-primary mb-3">
                    <i class="fas fa-bolt me-2"></i>Quick Actions
                </h5>
                <div class="row">
                    <div class="col-md-3 text-center mb-3">
                        <button class="btn btn-outline-primary w-100" onclick="bulkAction('activate')">
                            <i class="fas fa-user-check fa-2x mb-2 d-block"></i>
                            Bulk Activate
                        </button>
                    </div>
                    <div class="col-md-3 text-center mb-3">
                        <button class="btn btn-outline-warning w-100" onclick="bulkAction('suspend')">
                            <i class="fas fa-user-lock fa-2x mb-2 d-block"></i>
                            Bulk Suspend
                        </button>
                    </div>
                    <div class="col-md-3 text-center mb-3">
                        <button class="btn btn-outline-info w-100" onclick="exportUsers()">
                            <i class="fas fa-file-export fa-2x mb-2 d-block"></i>
                            Export Data
                        </button>
                    </div>
                    <div class="col-md-3 text-center mb-3">
                        <button class="btn btn-outline-success w-100" data-bs-toggle="modal" data-bs-target="#addUserModal">
                            <i class="fas fa-user-plus fa-2x mb-2 d-block"></i>
                            Add User
                        </button>
                    </div>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="search-filters">
                <form method="GET" class="row align-items-end">
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-search me-2"></i>Search Users
                        </label>
                        <input type="text" class="form-control" name="search" 
                               value="<?php echo htmlspecialchars($search_term); ?>" 
                               placeholder="Search by name or email...">
                    </div>
                    
                    <div class="col-md-2 mb-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-user-tag me-2"></i>Role
                        </label>
                        <select class="form-select" name="role">
                            <option value="all" <?php echo $role_filter == 'all' ? 'selected' : ''; ?>>All Roles</option>
                            <option value="1" <?php echo $role_filter == '1' ? 'selected' : ''; ?>>Admin</option>
                            <option value="2" <?php echo $role_filter == '2' ? 'selected' : ''; ?>>Renter</option>
                            <option value="3" <?php echo $role_filter == '3' ? 'selected' : ''; ?>>Both</option>
                            <option value="4" <?php echo $role_filter == '4' ? 'selected' : ''; ?>>Owner</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2 mb-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-toggle-on me-2"></i>Status
                        </label>
                        <select class="form-select" name="status">
                            <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="suspended" <?php echo $status_filter == 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                            <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-sort me-2"></i>Sort By
                        </label>
                        <select class="form-select" name="sort">
                            <option value="newest" <?php echo $sort_by == 'newest' ? 'selected' : ''; ?>>Newest First</option>
                            <option value="oldest" <?php echo $sort_by == 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                            <option value="name_asc" <?php echo $sort_by == 'name_asc' ? 'selected' : ''; ?>>Name A-Z</option>
                            <option value="name_desc" <?php echo $sort_by == 'name_desc' ? 'selected' : ''; ?>>Name Z-A</option>
                            <option value="email_asc" <?php echo $sort_by == 'email_asc' ? 'selected' : ''; ?>>Email A-Z</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2 mb-3">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-2"></i>Filter
                        </button>
                    </div>
                </form>
            </div>

            <!-- Bulk Actions -->
            <div class="bulk-actions">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1 text-primary">
                            <i class="fas fa-layer-group me-2"></i>Bulk Actions
                        </h6>
                        <p class="text-muted small mb-0">Select users to perform bulk operations</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-primary btn-sm" onclick="selectAll()">
                            <i class="fas fa-check-square me-1"></i>Select All
                        </button>
                        <button class="btn btn-outline-secondary btn-sm" onclick="clearSelection()">
                            <i class="fas fa-square me-1"></i>Clear All
                        </button>
                    </div>
                </div>
            </div>

            <!-- Users List -->
            <?php if(empty($users)): ?>
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <h4 class="text-muted">No users found</h4>
                    <p class="text-muted">No users match your current filter criteria. Try adjusting your search filters.</p>
                    <a href="users.php" class="btn btn-primary btn-lg" style="border-radius: 25px;">
                        <i class="fas fa-refresh me-2"></i>Show All Users
                    </a>
                </div>
            <?php else: ?>
                <?php foreach($users as $user): ?>
                <div class="user-card card <?php echo strtolower(getRoleName($user['User_Role'])); ?>">
                    <div class="user-status">
                        <span class="badge status-badge <?php echo strtolower($user['User_Status']); ?>">
                            <?php echo htmlspecialchars($user['User_Status']); ?>
                        </span>
                    </div>
                    
                    <div class="card-body p-4">
                        <div class="row">
                            <div class="col-md-1">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="<?php echo $user['UserID']; ?>" name="selected_users[]">
                                </div>
                            </div>
                            
                            <div class="col-md-2">
                                <div class="user-avatar">
                                    <?php echo strtoupper(substr($user['User_Name'], 0, 2)); ?>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="d-flex align-items-center mb-2">
                                    <span class="role-badge <?php echo strtolower(getRoleName($user['User_Role'])); ?> me-2">
                                        <?php echo getRoleName($user['User_Role']); ?>
                                    </span>
                                    <small class="text-muted">
                                        ID: #<?php echo str_pad($user['UserID'], 6, '0', STR_PAD_LEFT); ?>
                                    </small>
                                </div>
                                
                                <h5 class="mb-1"><?php echo htmlspecialchars($user['User_Name']); ?></h5>
                                <p class="text-muted mb-2">
                                    <i class="fas fa-envelope me-1"></i>
                                    <?php echo htmlspecialchars($user['User_Email']); ?>
                                </p>
                                
                                <div class="user-info">
                                    <div class="row">
                                        <div class="col-6">
                                            <small class="text-muted">Joined:</small><br>
                                            <small><?php echo date('M j, Y', strtotime($user['User_CreatedAt'])); ?></small>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted">Last Login:</small><br>
                                            <small><?php echo date('M j, Y', strtotime($user['User_CreatedAt'])); ?></small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="user-stats">
                                    <div class="row">
                                        <div class="col-4 text-center">
                                            <div class="h6 mb-0"><?php echo $user['total_bookings'] ?? 0; ?></div>
                                            <small>Bookings</small>
                                        </div>
                                        <div class="col-4 text-center">
                                            <div class="h6 mb-0"><?php echo $user['total_products'] ?? 0; ?></div>
                                            <small>Products</small>
                                        </div>
                                        <div class="col-4 text-center">
                                            <div class="h6 mb-0">₱<?php echo number_format($user['total_spent'] ?? 0, 0); ?></div>
                                            <small>Spent</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="d-flex flex-column gap-2">
                                    <!-- Status Actions -->
                                    <?php if($user['User_Status'] == 'Active'): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $user['UserID']; ?>">
                                            <input type="hidden" name="new_status" value="Inactive">
                                            <button type="submit" name="update_user_status" class="btn action-btn suspend w-100">
                                                <i class="fas fa-user-lock me-1"></i>Suspend
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $user['UserID']; ?>">
                                            <input type="hidden" name="new_status" value="Active">
                                            <button type="submit" name="update_user_status" class="btn action-btn activate w-100">
                                                <i class="fas fa-user-check me-1"></i>Activate
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <!-- Role Change -->
                                    <div class="dropdown">
                                        <button class="btn action-btn edit w-100 dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                            <i class="fas fa-user-edit me-1"></i>Change Role
                                        </button>
                                        <ul class="dropdown-menu">
                                            <?php if($user['User_Role'] != 1): ?>
                                                <li><a class="dropdown-item" href="#" onclick="changeRole(<?php echo $user['UserID']; ?>, 1)">Make Admin</a></li>
                                            <?php endif; ?>
                                            <?php if($user['User_Role'] != 2): ?>
                                                <li><a class="dropdown-item" href="#" onclick="changeRole(<?php echo $user['UserID']; ?>, 2)">Make Renter</a></li>
                                            <?php endif; ?>
                                            <?php if($user['User_Role'] != 3): ?>
                                                <li><a class="dropdown-item" href="#" onclick="changeRole(<?php echo $user['UserID']; ?>, 3)">Make Both</a></li>
                                            <?php endif; ?>
                                            <?php if($user['User_Role'] != 4): ?>
                                                <li><a class="dropdown-item" href="#" onclick="changeRole(<?php echo $user['UserID']; ?>, 4)">Make Owner</a></li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                    
                                    <!-- Delete User (Only for non-admins) -->
                                    <?php if($user['User_Role'] != 1): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $user['UserID']; ?>">
                                            <button type="submit" name="delete_user" class="btn action-btn delete w-100"
                                                    onclick="return confirm('Are you sure you want to delete this user?')">
                                                <i class="fas fa-trash me-1"></i>Delete
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <!-- View Details -->
                                    <button class="btn btn-outline-primary btn-sm w-100" onclick="viewUserDetails(<?php echo $user['UserID']; ?>)" style="border-radius: 15px;">
                                        <i class="fas fa-eye me-1"></i>View Details
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- Pagination -->
                <div class="d-flex justify-content-center mt-4">
                    <nav>
                        <ul class="pagination">
                            <li class="page-item"><a class="page-link" href="#"><i class="fas fa-chevron-left"></i></a></li>
                            <li class="page-item active"><a class="page-link" href="#">1</a></li>
                            <li class="page-item"><a class="page-link" href="#">2</a></li>
                            <li class="page-item"><a class="page-link" href="#">3</a></li>
                            <li class="page-item"><a class="page-link" href="#"><i class="fas fa-chevron-right"></i></a></li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="border-radius: 20px;">
                <div class="modal-header border-0">
                    <h5 class="modal-title text-primary">
                        <i class="fas fa-user-plus me-2"></i>Add New User
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addUserForm">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email Address <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">User Role <span class="text-danger">*</span></label>
                                <select class="form-select" required>
                                    <option value="">Select Role</option>
                                    <option value="1">Admin</option>
                                    <option value="2">Renter</option>
                                    <option value="3">Both</option>
                                    <option value="4">Owner</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status <span class="text-danger">*</span></label>
                                <select class="form-select" required>
                                    <option value="Active">Active</option>
                                    <option value="Inactive">Inactive</option>
                                    <option value="Pending">Pending</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" required>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary">
                        <i class="fas fa-user-plus me-2"></i>Add User
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar toggle for mobile
        document.getElementById('sidebarToggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });

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

        // Select all checkboxes
        function selectAll() {
            const checkboxes = document.querySelectorAll('input[name="selected_users[]"]');
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            checkboxes.forEach(cb => cb.checked = !allChecked);
        }

        // Clear all selections
        function clearSelection() {
            document.querySelectorAll('input[name="selected_users[]"]').forEach(cb => cb.checked = false);
        }

        // Bulk actions
        function bulkAction(action) {
            const selected = Array.from(document.querySelectorAll('input[name="selected_users[]"]:checked'));
            if (selected.length === 0) {
                alert('Please select at least one user.');
                return;
            }
            
            if (confirm(`Are you sure you want to ${action} ${selected.length} user(s)?`)) {
                alert(`${action} operation will be implemented with proper backend handling.`);
            }
        }

        // Export users
        function exportUsers() {
            alert('Export functionality will be implemented with proper backend support.');
        }

        // View user details
        function viewUserDetails(userId) {
            alert('User details modal will be implemented. User ID: ' + userId);
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

        // Animate user cards on load
        window.addEventListener('load', function() {
            const userCards = document.querySelectorAll('.user-card');
            userCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'all 0.6s ease';
                
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });

        // Status badge hover effects
        document.querySelectorAll('.status-badge').forEach(badge => {
            badge.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.1)';
            });
            
            badge.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1)';
            });
        });

        // Add user form validation
        document.getElementById('addUserForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const passwords = this.querySelectorAll('input[type="password"]');
            if (passwords[0].value !== passwords[1].value) {
                alert('Passwords do not match.');
                return;
            }
            alert('Add user functionality will be implemented with proper backend handling.');
        });
    </script>
</body>
</html>