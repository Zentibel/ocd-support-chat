var socket;

$(document).ready(function() {

var socket = io('');


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

socket.on('chat-message', function (data) {
    getLatestMessages(ROOM_ID);
});

});
