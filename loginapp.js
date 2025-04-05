const logregBox = document.querySelector('.login-registration-box');
const loginLink = document.querySelector('.login-link');
const registerLink = document.querySelector('.register-link');

const background = document.querySelector('.background');
const containerBackground = document.querySelector('.login-container');

registerLink.addEventListener('click', () => {
    logregBox.classList.add('active');
    background.classList.add('register');
    containerBackground.classList.add('register');
    document.title = 'Register';
});

loginLink.addEventListener('click', () => {
    logregBox.classList.remove('active');
    background.classList.remove('register');
    containerBackground.classList.remove('register');
    document.title = 'Login';
});

function showHide(passwordID, eyeID, showID, hideID){
    const loginpassword = document.getElementById(passwordID);
    const logineye = document.getElementById(eyeID);

    logineye.addEventListener("click", function () {
        const type = loginpassword.getAttribute("type") === "password" ? "text" : "password";
        loginpassword.setAttribute("type", type);
    
        if(type === "password"){
            this.src = "images/show.png";
        }else{
            this.src = "images/hide.png";
        }
    });
}

function showHideIconToggle(passwordID, eyeID){
    const passwordfield = document.getElementById(passwordID);
    const eyeicon = document.getElementById(eyeID);
    let iconClicked = false;

    passwordfield.addEventListener("focus", function(){
        eyeicon.classList.remove('hide');
        iconClicked = true;
    });
    passwordfield.addEventListener("blur", function(){
        if(passwordfield.value.length === 0){
            iconClicked = false;
            if(iconClicked == false){
                eyeicon.classList.add('hide');
            }
        }
    });
}

showHideIconToggle("loginpassword","logineye");
showHideIconToggle("regpassword", "regpeye");
showHideIconToggle("regconfirmpassword", "regcpeye");

showHide("loginpassword", "logineye", "images/show.png", "images/hide.png");
showHide("regpassword", "regpeye", "images/show.png", "images/hide.png");
showHide("regconfirmpassword", "regcpeye", "images/show.png", "images/hide.png");
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