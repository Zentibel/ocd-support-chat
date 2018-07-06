#!/usr/bin/env node

const uuidv4 = require('uuid/v4');
var app = require('http').createServer(handler)
var io = require('socket.io')(app);
var redis = require("redis");
var redisClient = redis.createClient();
var sub = redis.createClient();
var pub = redis.createClient();

app.listen(9999);

sub.subscribe('new-message');
sub.on('message', function (channel, chatId) {
    console.log('Room ID: ' + chatId);
    io.to(chatId).emit('new-message');
    if (chatId == 'dd0c62bd-c4f2-4286-affa-256bfcc93955') {
        redisClient.zrange(['chat:messages:' + chatId, '-1', '-1'], function(err, msgId) {
            console.log('Message ID: ' + msgId[0]);
            redisClient.hgetall(msgId[0], function(err, msg) {
                redisClient.hget('messageCounts', msg['sender'], function(err, count) {
                    if (count > 1) {
                        return;
                    }

                    setTimeout(function() {
                        var msgId = uuidv4();
                        var now = new Date().getTime() / 1000;
                        var data = {
                            id: msgId,
                            sender: 'b3dd9e79-de3b-4d55-8c94-b9b5df5d7769',
                            roomId: 'dd0c62bd-c4f2-4286-affa-256bfcc93955',
                            message: "Hello! Welcome to the “OChatD” main support room!\n\nThis chat is a very friendly, active, world-wide community and there is *usually* someone around to respond, so if you don’t hear back immediately, stick around – someone will be here soon to answer your questions.\n\nIn the meantime, please check out the [Welcome Page](/about) for some basic chat rules and advice.\n\nFor any off-topic discussion unrelated to OCD support, we invite you to join us in the [general chat](/general)!",
                            timestamp: now,
                            ip: '127.0.0.1',
                        };
                        redisClient.hmset('message:'+msgId, data);
                        redisClient.zadd('chat:messages:dd0c62bd-c4f2-4286-affa-256bfcc93955', now, 'message:'+msgId);
                        redisClient.publish('new-message', 'dd0c62bd-c4f2-4286-affa-256bfcc93955');
                    }, 2000);
                });
            })
        });
    }
});

function handler (req, res) {
    res.writeHead(200);
    res.end('Hello. I am the server that makes the messages show up fast. :)');
}

io.on('connection', function (socket) {

    socket.on('join-room', function(room) {
        socket.join(room);
        socket.room = room;
    });

    socket.on('media-upload-progress', function(data) {
        console.log('Media upload progress: ' + data.percent + '% ' + data.mediaId);
        io.to(socket.room).emit('media-upload-progress', data);
    });

    socket.on('message-upload-complete', function(messageId) {
        pub.publish('message-to-gliph-'+socket.room, 'message:'+messageId);
    });
    socket.on('media-upload-complete', function(mediaId) {
        console.log('Media uploaded: ' + mediaId);
        io.to(socket.room).emit('media-upload-complete', mediaId);
    });

});
