<aside id="sidebar" class="border-end bg-secondary border-light">
    <div class="sidebar-heading text-white fw-bold py-4 px-3 border-bottom border-light opacity-50">
        <i class="fas fa-chart-line me-2"></i> IT Reports
    </div>

    <div class="list-group list-group-flush pt-2">
        <a class="list-group-item list-group-item-action bg-secondary text-white fw-bold" href="dashboard.php">
            <i class="fas fa-tachometer-alt me-2"></i> Dashboard
        </a>

        <?php if (has_role(['Admin', 'Zone', 'Woreda'])): ?>
            <a class="list-group-item list-group-item-action bg-secondary text-white" href="trainings.php">
                <i class="fas fa-calendar-check me-2"></i> Trainings
            </a>
        <?php endif; ?>

        <?php if (has_role(['Zone', 'Woreda'])): ?>
            <a class="list-group-item list-group-item-action bg-secondary text-white" href="training_categories.php">
                <i class="fas fa-tags me-2"></i> Categories
            </a>
            <a class="list-group-item list-group-item-action bg-secondary text-white" href="trainees.php">
                <i class="fas fa-users me-2"></i> Trainees
            </a>
        <?php endif; ?>

        <?php if (has_role(['Zone'])): ?>
            <a class="list-group-item list-group-item-action bg-secondary text-white" href="zone_reports.php">
                <i class="fas fa-map-marker-alt me-2"></i> Zone Oversight
            </a>            
            <a class="list-group-item list-group-item-action bg-secondary text-white" href="districts.php">
                <i class="fas fa-city me-2"></i> Districts
            </a>
        <?php endif; ?>

        <?php if (has_role('Admin')): ?>
            <a class="list-group-item list-group-item-action bg-secondary text-white" href="users.php">
                <i class="fas fa-users-cog me-2"></i> Users
            </a>
        <?php endif; ?>
    </div>
</aside>