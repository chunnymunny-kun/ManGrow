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
showHide("loginpassword", "logineye", "images/show.png", "images/hide.png");