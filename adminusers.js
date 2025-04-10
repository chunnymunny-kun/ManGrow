function importFormToggle(ID){
    var form = document.getElementById(ID);
    if (form.style.display === "none") {
        form.style.display = "block";
    } else {
        form.style.display = "none";
    }
}