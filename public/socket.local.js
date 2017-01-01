$(document).ready(function() {
var socket = io('localhost:9999');


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
      getLatestMessages(data);
    console.log(data);
  });
});
