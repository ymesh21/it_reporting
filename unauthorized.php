<?php
// unauthorized.php
require_once 'auth_check.php';
include_once 'inc/header.php';
include_once 'inc/sidebar.php';
?>
<div id="main">
    <?php include_once 'inc/nav.php'; ?>
    <div class="container-fluid pt-5 mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                        <h3 class="text-danger">Access Denied</h3>
                        <p class="text-muted">You do not have permission to access this page.</p>
                        <a href="dashboard.php" class="btn btn-primary mt-3">
                            <i class="fas fa-home me-2"></i>Return to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include_once 'inc/footer.php'; ?>
</body>

</html>