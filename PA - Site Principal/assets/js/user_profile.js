const errAlert = document.getElementById('error-alert');
const profileContainer = document.getElementById('profile-container');
const btnAddFriend = document.getElementById('btn-add-friend');
const modalFriendReq = document.getElementById('modal-friend-request');
const friendReqError = document.getElementById('friend-request-error');

const userTypes = { 1: "Basic User", 2: "Association", 3: "Admin" };

async function authedFetch(url, options = {}) {
    let token = localStorage.getItem('jwt_token');
    if(!token) {
        throw new Error("You must be logged in.");
    }
    const headers = { 'Authorization': `Bearer ${token}` };
    if (options.body && !(options.body instanceof FormData)) {
        headers['Content-Type'] = 'application/json';
    }
    options.headers = { ...headers, ...(options.headers || {}) };
    return fetch(`http://${window.location.hostname}:9999${url}`, options);
}

async function loadProfile() {
    try {
        const res = await authedFetch(`/profile/${encodeURIComponent(window.targetUsername)}`);
        if (!res.ok) { throw new Error("User not found or an error occurred."); }
        
        const user = await res.json();
        
        document.getElementById('profile-username').textContent = user.username;
        document.getElementById('profile-firstname').textContent = user.first_name || 'N/A';
        document.getElementById('profile-lastname').textContent = user.last_name || 'N/A';
        document.getElementById('profile-score').textContent = user.upcycling_score || 0;
        document.getElementById('profile-type').textContent = userTypes[user.user_type] || "Unknown";
        document.getElementById('modal-target-username').textContent = user.username;
        
        profileContainer.classList.remove('d-none');
        
        try {
            const meRes = await authedFetch(`/users/public/me`);
        } catch(e) {}
        
        btnAddFriend.style.display = 'inline-flex';
    } catch (e) {
        errAlert.textContent = e.message;
        errAlert.classList.remove('d-none');
    }
}

function openModal(m) {
    m.classList.add('visible');
    m.setAttribute('aria-hidden', 'false');
}
function closeModal(m) {
    m.classList.remove('visible');
    m.setAttribute('aria-hidden', 'true');
}

btnAddFriend.onclick = () => {
    document.getElementById('friend-request-message').value = '';
    friendReqError.classList.add('d-none');
    openModal(modalFriendReq);
};

document.querySelectorAll('.modal-close, .modal-close-btn').forEach(btn => {
    btn.onclick = (e) => closeModal(e.target.closest('.modal-overlay'));
});

document.getElementById('btn-confirm-friend-request').onclick = async () => {
    const message = document.getElementById('friend-request-message').value.trim();
    try {
        const res = await authedFetch('/friends', {
            method: 'POST',
            body: JSON.stringify({ username: window.targetUsername, message: message })
        });
        
        if (res.ok) {
            closeModal(modalFriendReq);
            alert("Friend request sent!");
            btnAddFriend.disabled = true;
            btnAddFriend.innerHTML = '<i class="fas fa-check"></i> Request Sent';
        } else {
            const data = await res.json();
            throw new Error(data.error || "Failed to send request.");
        }
    } catch (e) {
        friendReqError.textContent = e.message;
        friendReqError.classList.remove('d-none');
    }
};

document.addEventListener('DOMContentLoaded', loadProfile);