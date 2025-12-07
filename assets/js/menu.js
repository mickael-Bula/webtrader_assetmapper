document.addEventListener('DOMContentLoaded', () => {
    const toggleBtn = document.querySelector('.menu-toggle-btn');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', () => {
            document.body.classList.toggle('drawer-open');
        });
    }

    // Fermer le menu si l'on clique en dehors (pour simuler l'overlay)
    document.querySelector('.app-main').addEventListener('click', () => {
        if (document.body.classList.contains('drawer-open')) {
            document.body.classList.remove('drawer-open');
        }
    });
});
