// global toast helper used across site
function showToast(msg, timeout) {
    timeout = timeout || 5000;
    var t = document.createElement('div');
    t.className = 'toast';
    t.innerText = msg;
    document.body.appendChild(t);
    setTimeout(function() {
        t.style.transition = 'opacity 0.3s';
        t.style.opacity = '0';
        setTimeout(function(){ try{ document.body.removeChild(t); } catch(e){} }, 350);
    }, timeout);
}
