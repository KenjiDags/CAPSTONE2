// show password function
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    if(input.type === "password") {
        input.type = "text";
    } else {
        input.type = "password";
    }
}

// change password modal
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('changePasswordModal');
    const openBtn = document.getElementById('openChangePassword');
    const closeBtn = document.getElementById('closeChangePassword');
    const form = document.getElementById('changePasswordForm');
    const messageDiv = document.getElementById('changePasswordMessage');

    if (!modal || !openBtn || !closeBtn || !form || !messageDiv) return; 

    openBtn.addEventListener('click', e => {
        e.preventDefault();
        modal.style.display = 'block';
    });

    closeBtn.addEventListener('click', () => modal.style.display = 'none');
    window.addEventListener('click', e => {
        if (e.target === modal) modal.style.display = 'none';
    });

    form.addEventListener('submit', async e => {
        e.preventDefault();

        const newPassword = document.getElementById('newPassword').value;
        const confirmPassword = document.getElementById('confirmPassword').value;

        if (newPassword !== confirmPassword) {
            messageDiv.textContent = 'Passwords do not match!';
            messageDiv.style.color = 'red';
            return;
        }

        const formData = new FormData(form);

        try {
            const response = await fetch('change_password.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                messageDiv.textContent = 'Password changed successfully!';
                messageDiv.style.color = 'green';
                form.reset();
            } else {
                messageDiv.textContent = result.message || 'Failed to change password';
                messageDiv.style.color = 'red';
            }
        } catch (err) {
            messageDiv.textContent = 'Error connecting to server.';
            messageDiv.style.color = 'red';
            console.error(err);
        }
    });
});