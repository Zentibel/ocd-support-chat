var socket;

$(document).ready(function() {

socket = io('localhost:9999');


socket.on('connect', function(){
    $('nav svg.logo, nav img.logo')
        .removeClass('disconnected')
        .addClass('connected');
});

socket.on('disconnect', function(){
    $('nav svg.logo')
        .removeClass('connected')
        .addClass('disconnected');
});

});
