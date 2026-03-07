if (localStorage.getItem('theme') === 'dark') {
    document.body.classList.add('dark-mode');
}

const darkToggle = document.getElementById('dark-toggle');
if (darkToggle) {
    darkToggle.addEventListener('click', function(e) {
        const isDark = document.body.classList.contains('dark-mode');
        const x = e.clientX;
        const y = e.clientY;
        const endRadius = Math.hypot(
            Math.max(x, window.innerWidth - x),
            Math.max(y, window.innerHeight - y)
        );
        const clipPath = [
            `circle(0px at ${x}px ${y}px)`,
            `circle(${endRadius}px at ${x}px ${y}px)`
        ];

        const applyToggle = () => {
            document.body.classList.toggle('dark-mode');
            localStorage.setItem('theme', document.body.classList.contains('dark-mode') ? 'dark' : 'light');
        };

        if (!document.startViewTransition) {
            applyToggle();
            return;
        }

        const transition = document.startViewTransition(applyToggle);
        transition.ready.then(() => {
            document.documentElement.animate(
                { clipPath },
                {
                    duration: 500,
                    easing: 'ease-in-out',
                    pseudoElement: '::view-transition-new(root)'
                }
            );
        });
    });
}