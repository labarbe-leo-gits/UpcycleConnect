document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        var skel = document.getElementById('badges-skeleton');
        var real = document.getElementById('badges-real');
        if (skel) skel.style.display = 'none';
        if (real) real.style.display = 'flex';
    }, 800);
});