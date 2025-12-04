const logregBox = document.querySelector('.login-registration-box');
const loginLink = document.querySelector('.login-link');
const registerLink = document.querySelector('.register-link');

const background = document.querySelector('.background');
const containerBackground = document.querySelector('.login-container');

registerLink.addEventListener('click', () => {
    logregBox.classList.add('active');
    background.classList.add('register');
    containerBackground.classList.add('register');
    document.title = 'Verify Account';
});

loginLink.addEventListener('click', () => {
    logregBox.classList.remove('active');
    background.classList.remove('register');
    containerBackground.classList.remove('register');
    document.title = 'Login';
});

// Simplified show/hide password function
function setupPasswordToggle(passwordID, eyeID) {
    const passwordField = document.getElementById(passwordID);
    const eyeIcon = document.getElementById(eyeID);
    
    // Show/hide icon based on input focus and content
    passwordField.addEventListener('focus', () => {
        eyeIcon.classList.remove('hide');
    });
    
    passwordField.addEventListener('blur', () => {
        if (passwordField.value.length === 0) {
            eyeIcon.classList.add('hide');
        }
    });
    
    // Toggle password visibility
    eyeIcon.addEventListener('click', () => {
        const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordField.setAttribute('type', type);
        
        // Toggle between show/hide images
        eyeIcon.src = type === 'password' ? 'images/show.png' : 'images/hide.png';
    });
}

// Initialize for all password fields
setupPasswordToggle('loginpassword', 'logineye');
/*
const loginpassword = document.getElementById('loginpassword');
const logineye = document.getElementById('logineye');

logineye.addEventListener("click", function () {
    const type = loginpassword.getAttribute("type") === "password" ? "text" : "password";
    loginpassword.setAttribute("type", type);

    if(type === "password"){
        this.src = "images/show.png";
    }else{
        this.src = "images/hide.png";
    }
});
*/