#!/usr/bin/env python
from gliph import *
from pprint import pprint
import redis
import time
import os
import sys
import getopt

GLIPH_DATA_DIR  = '{0}/public/gliph-media'.format(os.path.dirname(os.path.dirname(os.path.realpath(__file__))))
UPLOAD_DATA_DIR = '{0}/public/uploads/default'.format(os.path.dirname(os.path.dirname(os.path.realpath(__file__))))

class GliphMedia:

    def __init__(self, media):
        self.thumbnail = ( media[0]['content_id'], media[0]['key'] )
        self.fullsize  = ( media[1]['content_id'], media[1]['key'] )

    def download(self, gliph):
        thumbnailPath = '{0}/{1}.png'.format(GLIPH_DATA_DIR, self.thumbnail[0])
        fullsizePath  = '{0}/{1}.png'.format(GLIPH_DATA_DIR, self.fullsize[0])

        if not os.path.isfile(thumbnailPath):
            thumbnailBytes = gliph.download_media_get(self.thumbnail[0], self.thumbnail[1])
            with open(thumbnailPath, 'w') as file_:
                file_.write(thumbnailBytes)

        if not os.path.isfile(fullsizePath):
            fullsizeBytes = gliph.download_media_get(self.fullsize[0], self.fullsize[1])
            with open(fullsizePath, 'w') as file_:
                file_.write(fullsizeBytes)


class GliphMessage:

    def __init__(self, message):
        now = time.time()
        self.id            = message['id']
        self.sender        = message['sender']
        self.created_on    = now + message['created_on']
        self.message_text  = ''
        self.media         = []

        self.parseMessageContent(message['content'])

    def parseMessageContent(self, content):
        if content['content_type'] == 'text/plain':
            self.message_text = content['content']
            return

        if content['content_type'] == 'multipart/related':
            [self.parseMessageContent(part) for part in content['content']]
            return

        if content['content_type'] == 'multipart/alternative':
            self.media.append(GliphMedia(content['content']))


class GliphMessagePoller:
    pollTime = 1

    def __init__(self, redisClient, gliphConnectionId, gliphSession, localChatId):
        self.redis             = redisClient
        self.redisPubSub       = redisClient.pubsub()
        self.gliphConnectionId = gliphConnectionId
        self.gliphSession      = gliphSession
        self.localChatId       = localChatId
        self.paginate          = False

        self.redisPubSub.subscribe('message-to-gliph-{0}'.format(sys.argv[2]))

    def sendMessages(self):
        messageToSend = self.redisPubSub.get_message()
        if not messageToSend or messageToSend['type'] != 'message':
            return

        print 'Message to send...'
        message = self.redis.hgetall(messageToSend['data'])
        sender  = self.redis.hgetall('user:{0}'.format(message['sender']))['username']
        imagesToSend = []
        if 'media' in message:
            print 'Media attached...'
            media = message['media'].split('#')
            for image in media:
                image = image.split('|')

                with open('{0}/{1}'.format(UPLOAD_DATA_DIR, image[0]), 'r') as content_file:
                    thumbnail = content_file.read()

                thumb = {'type': self._filenameToContentType(image[0]), 'data': thumbnail}

                if len(image) > 1:
                    with open('{0}/{1}'.format(UPLOAD_DATA_DIR, image[1]), 'r') as content_file:
                        fullsize = content_file.read()

                    full = {'type': self._filenameToContentType(image[1]), 'data': fullsize}
                    imagesToSend.append({'thumb': thumb, 'full': full});
                else:
                    imagesToSend.append({'thumb': thumb, 'full': thumb});

        if sender == 'pb.j':
            return
        messageText = "{0} says:\n\n{1}".format(sender, message['message'])
        self.gliphSession.send_message_multi(self.gliphConnectionId, text=messageText, image=imagesToSend)

    def _filenameToContentType(self, filename):
        if filename[-4:] == '.gif':
            return 'image/gif'
        if filename[-4:] == '.jpg':
            return 'image/jpeg'
        if filename[-4:] == '.png':
            return 'image/png'

    def getMessages(self):
        messages = self.gliphSession.messages(self.gliphConnectionId, paginate=self.paginate, limit=50)
        self.paginate = {'after': messages[0]['after']}
        if len(messages[1]) == 0:
            return # no new messages right now...
        self.saveMessages(messages[1])

    def saveMessages(self, messages):
        for message in messages:
            if message['sender'] == '55270d999e9ad15171548416':
                continue # skip ocdbot messages entirely
            message = GliphMessage(message)
            self.saveUser(message.sender)
            self.saveMessage(message)


    def saveMessage(self, message):
        redisKey = 'gliph:message:{0}'.format(message.id)
        if self.redis.exists(redisKey):
            return # we already have this message

        self.redis.hset(redisKey, 'id', message.id)
        self.redis.hset(redisKey, 'connection', self.gliphConnectionId)
        self.redis.hset(redisKey, 'sender', message.sender)
        self.redis.hset(redisKey, 'timestamp', message.created_on)
        self.redis.hset(redisKey, 'text', message.message_text)

        mediaList = []

        for media in message.media:
            media.download(self.gliphSession)
            mediaList.append('{0}|{1}'.format(media.thumbnail[0], media.fullsize[0]))

        if len(mediaList) > 0:
            self.redis.hset(redisKey, 'media', '#'.join(mediaList))

        print '!'
        redisChatKey = 'chat:messages:{0}'.format(self.localChatId)
        self.redis.zadd(redisChatKey, message.created_on, redisKey)
        self.redis.publish('new-message', self.localChatId)


    def saveUser(self, gliphUserId):
        redisKey = 'gliph:user:{0}'.format(gliphUserId)
        #if self.redis.exists(redisKey):
        #    return # we already have this user, todo: check last update?

        user = self.gliphSession.search_for_user_by_user_id(gliphUserId)

        stringFacets = (
            'Pseudonym',
            'Username',
            'First Name',
            'Last Name',
            'Email',
            'Phone Number',
            'Facebook Profile',
            'Twitter',
        )

        self.redis.hset(redisKey, 'id', gliphUserId)

        for facet in user['facets']:
            if facet['facet_type'] in stringFacets:
                self.redis.hset(redisKey, 'facet:{0}'.format(facet['facet_type']), facet['content']['content'])

            if facet['facet_type'] == 'Profile Photo':

                photo = GliphMedia(facet['content']['content'])
                photo.download(self.gliphSession)

                self.redis.hset(redisKey, 'photo:thumbnail', photo.thumbnail[0])
                self.redis.hset(redisKey, 'photo:fullsize', photo.fullsize[0])

    def start(self):
        while True:
            self.getMessages()
            self.sendMessages()
            time.sleep(GliphMessagePoller.pollTime)

r = redis.StrictRedis(host='localhost', port=6379, db=0)

client = GliphSession(debug=False, client='GliphBridge/0.0.1 (Shepherd)')
client.login(username='ocdbot', passphrase='gliphtest')

#gliphConnection = '58637bba3f47a502266696c3' # Test Room
pprint(sys.argv)
gliphConnection = sys.argv[1]
localChatId = sys.argv[2]
poll = GliphMessagePoller(r, gliphConnection, client, localChatId)
poll.start()
