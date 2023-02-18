function showEbanInfo(button) {
    let id = $(button).attr('id-data');
    let diva = '#diva-'+id;
    $(diva+'-tr').slideToggle();
    if($(diva).attr('is_slided') == 0) {
        $(diva).attr('is_slided', 1);
        $(diva).slideDown();
        $(diva).css('display', 'flex');
    } else {
        $(diva).attr('is_slided', 0);
        $(diva).slideUp();
    }
}

function GoTop() {
    if(document.documentElement.scrollTop > 450 || document.body.scrollTop > 450) {
        document.body.scrollTop = 200;
        document.documentElement.scrollTop = 200;
    }
}

function GoHome() {
    window.location.replace('index.php?all');
}

function ConfirmUnban(id, name, steamid) {
    let reason = prompt('Please type the reason why you would Kunban '+name+'['+steamid+']');
    let confirmMessage = 'Are you sure you want to Kunban '+name+'['+steamid+']';
    let confirmHandler = confirm(confirmMessage);
    if(confirmHandler == true) {
        UnBanByID(id, reason);
    }
}

function UnBanByID(id, reason) {
    var xmlResponse1 = new XMLHttpRequest();
    xmlResponse1.onreadystatechange = function() {
        if(this.readyState == 4 && this.status == 200) {
            $('#diva-'+id).html(xmlResponse2.responseText);
            
            let trDiva = document.getElementById('diva-tr-'+id);
            trDiva.className = "row-expired";
            let oldHtml = $('#length-'+id).html();
            let newHtml = (oldHtml + ' (Removed)');
            $('#length-'+id).html(newHtml);
        }
    };

    xmlResponse1.open("GET", "functions_url.php?oldid="+id+'&reason='+reason, true);
    xmlResponse1.send();

    /*var xmlResponse2 = new XMLHttpRequest();
    xmlResponse2.onreadystatechange = function() {
        if(this.readyState == 4 && this.status == 200) {
            $('#diva-'+id).html(xmlResponse2.responseText);
            
            let trDiva = document.getElementById('diva-tr-'+id);
            trDiva.className = "row-expired";
            let oldHtml = $('#length-'+id).html();
            let newHtml = (oldHtml + ' (Removed)');
            $('#length-'+id).html(newHtml);
        }
    };

    xmlResponse2.open("GET", "functions_url.php?id="+id, true);
    xmlResponse2.send();*/
}

function ViewPlayerHistory(steamid, method) {
    let url = "index.php?all=true&s="+steamid+"&m="+method;
    window.location.replace(url);
}

function RebanFromID(id) {
    let url = "manage.php?reban&oldid="+id;
    window.location.replace(url);
}

function EditFromID(id) {
    let url = "manage.php?edit&oldid="+id;
    window.location.replace(url);
}

function addNewEban(playerName, playerSteamID, length, reason) {
    var xmlResponse = new XMLHttpRequest();
    xmlResponse.onreadystatechange = function() {
        if(this.readyState == 4 && this.status == 200) {
            $('.error').html(xmlResponse.responseText);
        }
    };

    let url = "functions_url.php?add=1&playerName="+playerName+'&playerSteamID='+playerSteamID+'&length='+length+'&reason='+reason;
    xmlResponse.open("GET", url, true);
    xmlResponse.send();
}

function EditEban(id, playerName, playerSteamID, length, reason) {
    var xmlResponse1 = new XMLHttpRequest();
    xmlResponse1.onreadystatechange = function() {
        if(this.readyState == 4 && this.status == 200) {
            $('.error').html(xmlResponse1.responseText);
        }
    };

    let url = "functions_url.php?edit=1&id="+id+'&playerName='+playerName+'&playerSteamID='+playerSteamID+'&length='+length+'&reason='+reason;
    xmlResponse1.open("GET", url, true);
    xmlResponse1.send();
}

function RemoveEbanFromDBCheck(id) {
    let confirmMessage = "Are you sure you want to delete this Eban from DB? ID: "+id;
    let confirmHandler = confirm(confirmMessage);
    if(confirmHandler == true) {
        RemoveEbanFromDB(id);
    }
}

function RemoveEbanFromDB(id) {
    var xmlResponse1 = new XMLHttpRequest();
    xmlResponse1.onreadystatechange = function() {
        if(this.readyState == 4 && this.status == 200) {
            $('.hide').html(xmlResponse1.responseText);
        }
    };

    xmlResponse1.open("GET", "functions_url.php?delete=1&deleteid="+id, true);
    xmlResponse1.send();
}

function setActive(num) {
    /* 0 = All Ebans, 1 = Active Ebans, 2 = Expired Ebans, 3 = Add Eban, 4 = Web Logs, 5 = Server Logs */
    if(num == 0) {
        const bar = document.getElementById("allEbans");
        bar.className = "active";
    } else if(num == 1) {
        const bar = document.getElementById("activeEbans");
        bar.className = "active";
    } else if(num == 2) {
        const bar = document.getElementById("expiredEbans");
        bar.className = "active";
    } else if(num == 4) {
        const bar = document.getElementById("weblogs");
        bar.className = "active";
    } else if(num == 5) {
        const bar = document.getElementById("srvlogs");
        bar.className = "active";
    } else {
        const bar = document.getElementById("addEban");
        bar.className = "active";
    }
}

function Login() {
    window.location.replace('src/login-init.php');
}

$(function() {
    $('.search-modal-btn-open').on('click', function(evt) {
        if($(this).attr('id') == "main-search") {
            setModalSearch("all");
        } else {
            let type = $(this).attr('data-page');
            setModalSearch(type);
        }

        $('.search-modal-body').toggle();
    });

    $('.search-modal-btn-close').on('click', function() {
        $('.search-modal-body').toggle();
    });
});

function setModalSearch(type) {
    $('#hideInput').attr('name', type);
}

window.addEventListener('click', function(e) {
    if(e.target.className == "search-modal-body") {
        $('.search-modal-body').toggle();
    }
});

function showEbanWindowInfo(type, playerName = "", playerSteamID = "", reason = "", length = "") {
    /* type values: 0 = Add Eban, 1 = Edit Eban, 2 = Unban Eban, 3 = Delete Eban */
    const titles = [
        "Eban Added",
        "Eban Edited",
        "Eban Unbanned",
        "Eban Deleted"
    ];

    const title = titles[type];
    $('#action-header-text').html(title+" <i class='fa-solid fa-check' style='color: var(--button-success);'></i>");

    let html = "";
    if(playerName[0]) {
        html += "<li>";
        html += "<span>";
        html += "<i class='fas fa-user'></i> Player";
        html += "</span>";
        html += "<span>";
        html += playerName;
        html += "</span>";
        html += "</li>";
    }

    if(playerSteamID[0]) {
        html += "<li>";
        html += "<span>";
        html += "<i class='fab fa-steam-symbol'></i> Steam ID";
        html += "</span>";
        html += "<span>";
        html += playerSteamID;
        html += "</span>";
        html += "</li>";
    }

    if(reason[0]) {
        html += "<li>";
        html += "<span>";
        html += "<i class='fas fa-question'></i> Reason";
        html += "</span>";
        html += "<span>";
        html += reason;
        html += "</span>";
        html += "</li>";
    }

    if(length[0]) {
        html += "<li>";
        html += "<span>";
        html += "<i class='fas fa-hourglass-half'></i> Duration";
        html += "</span>";
        html += "<span>";
        html += length;
        html += "</span>";
        html += "</li>";
    }

    $('.Eban-action-window .info .Eban_details').html(html);

    $('.Eban-action-window').css('display', 'block');
}

function CloseWindow() {
    $('.Eban-action-window').css('display', 'none');
}
