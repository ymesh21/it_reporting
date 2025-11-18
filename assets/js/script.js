document.addEventListener('DOMContentLoaded', function() {
    const toggleButton = document.getElementById('toggleBtn');
    const sidebar = document.getElementById('sidebar');

    if (toggleButton && sidebar) {
        toggleButton.addEventListener('click', function() {
            // 1. Toggle the 'expanded' class on the sidebar
            sidebar.classList.toggle('collapsed'); 
            
            // 2. Change the icon (Optional, but good UX)
            if (sidebar.classList.contains('collapsed')) {
                // If collapsed, show the menu icon (bars)
                toggleButton.classList.replace('fa-times', 'fa-bars'); 
            } else {
                // If open, show the close icon (times)
                toggleButton.classList.replace('fa-bars', 'fa-times');
            }
        });
    }

    // You can remove the window.resize and screen size logic 
    // from the previous example unless you want a desktop default state.
});