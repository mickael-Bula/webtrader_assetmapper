document.addEventListener('DOMContentLoaded', function() {
    const toggleButton = document.getElementById('toggle-icon-color');

    if (toggleButton) {
        toggleButton.addEventListener('click', function() {
            // Bascule entre les deux classes
            if (this.classList.contains('icon-off')) {
                this.classList.remove('icon-off');
                this.classList.add('icon-on');
            } else {
                this.classList.remove('icon-on');
                this.classList.add('icon-off');
            }
        });
    }
});
