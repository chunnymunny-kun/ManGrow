const ToggleButton = document.getElementById('toggle-btn')
const sidebar = document.getElementById('sidebar')



function SidebarToggle(){
    sidebar.classList.toggle('close')
    ToggleButton.classList.toggle('rotate')

    CloseAllSubMenus()                                                                                                                                
}

function DropDownToggle(button){
    if(!button.nextElementSibling.classList.contains('show')){

        CloseAllSubMenus()
    }

    button.nextElementSibling.classList.toggle('show')
    button.classList.toggle('rotate')

    if(sidebar.classList.contains('close')){
        sidebar.classList.toggle('close')
        ToggleButton.toggle('rotate')
    }
}

function CloseAllSubMenus(){
    Array.from(sidebar.getElementsByClassName('show')).forEach(ul => {
        ul.classList.remove('show')
        ul.previousElementSibling.classList.remove('rotate')
    })
}

function handleResize() {
    if (window.innerWidth <= 800) {
        if (sidebar.classList.contains('close')) {
            SidebarToggle();
        }
    }
}

handleResize();
window.addEventListener('resize', handleResize);

