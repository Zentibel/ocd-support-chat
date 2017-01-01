#!/usr/bin/env node

var app = require('http').createServer(handler)
var io = require('socket.io')(app);
var redis = require("redis");
var sub = redis.createClient();
var redisClient = redis.createClient();

app.listen(9999);

latestMessages = {};

sub.subscribe('new-message');
//redisClient.publish('message-to-gliph', "Shepherd: testing");
sub.on('message', function (channel, chatId) {
    console.log(chatId);

    io.emit('chat-message', chatId);
    //messages = redisClient.zrangebyscore([message, '-inf', '+inf'], function(err, resp) {
    //    console.log(message);
    //    console.log(err);
    //    console.log(resp);
    //    socket.emit('news', { channel: resp });
    //});
});

function handler (req, res) {
    res.writeHead(200);
    res.end('Hello. I am the server that makes the messages show up fast. :)');
}

io.on('connection', function (socket) {

});
