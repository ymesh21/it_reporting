<nav class="navbar navbar-expand-lg bg-body-tertiary">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">
            <span id="toggleBtn" class="fas fa-times"></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent"
            aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarSupportedContent">
            <div class="navbar-nav me-auto mb-2 mb-lg-0">
                <span class="navbar-brand">Reporting system</span>
            </div>
            <div class="dropdown">
                <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                    <?php echo $_SESSION['user_name'] . ' ('.$_SESSION['user_role'].')'; ?>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="#">Action</a></li>
                    <li><a class="dropdown-item" href="#">Another action</a></li>
                    <?php if ($_SESSION['logged_in']): ?>
                        <li><a class="dropdown-item text-danger" href="logout.php">Signout</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</nav>