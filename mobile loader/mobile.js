var ah = $('.app-header'),
spin = $(`<div style="z-index: 1030;" class="bg-secondary d-none position-fixed w-100">
    <span class="my-auto mx-auto text-primary">
        <i class="fa fa-2x"></i>
    </span>
</div>`).insertBefore(ah);

function showLoader(e){
    var touch = e.targetTouches[0],
    clientY = touch.clientY;
    if (clientY >= 0 && clientY <= 200) {
        $(".app-header").css("margin-top", clientY+"px");
        $("#page").css("margin-top", clientY+"px");
        spin.removeClass("d-none").addClass("d-flex").css("height", clientY+"px");
        
        if (clientY >= 130){
            spin.find("i").removeClass("fa-refresh").addClass("fa-spinner fa-pulse");
        } else {
            spin.find("i").removeClass("fa-spinner fa-pulse").addClass("fa-refresh");
        }
    }
}

ah.on('touchstart', function(e){
    //if (window.pageYOffset === 0) {
        var touch = e.targetTouches[0];
        console.log("touchstart", touch);
        
        $("body").css("overflow", "hidden");
        
        document.addEventListener( "touchmove", showLoader);
        
        document.addEventListener( "touchend", function (e) {
            if (spin.find("i").hasClass("fa-spinner")) window.location=window.location;
            document.removeEventListener('touchmove', showLoader);
            $("body").css("overflow", "auto");
            $(".app-header").css("margin-top", 0);
            $("#page").css("margin-top", 0);
            spin.addClass("d-none").removeClass("d-flex").css("height", 0);
        }, { once: true });
    //}
});