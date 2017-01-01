# Chat site with Gliph integration


---

## Users

#### Hash `user:{userId}`

* `id`
* `username`
* `passwordHash`

#### Hash `index:usernames`

* `{username}`: `{userId}`

---

## Communities

#### Hash `community:{communityId}`

* `id`
* `path`
* `name`
* `ownerId`

#### Hash `index:community-paths`

* `{path}`: `{communityId}`


#### Sorted Set `index:featured-communities`

* `{featuredCommunityIdN}`
* `{featuredCommunityIdN}`
* `{featuredCommunityIdN}`
* `{featuredCommunityIdN}`
* ...etc


#### Set `community:members:{communityId}`

* `{memberUserIdN}`
* `{memberUserIdN}`
* `{memberUserIdN}`
* `{memberUserIdN}`
* ...etc

---

## Chat Rooms

#### Sorted Set `chatroom:{chatroomId}`

* `{messageIdN}`
* `{messageIdN}`
* `{messageIdN}`
* `{messageIdN}`
* ...etc


#### Hash `message:{messageId}`

* `userId`
* `message`




---

# Gliph integration data

#### Hash: `gliph:user:{gliphId}`

* `gliph_id`
* `facet:Pseudonym`
* `facet:Username`
* `facet:First Name`
* `facet:Last Name`
* `facet:Email`
* `facet:Phone Number`
* `facet:Facebook Profile`
* `facet:Twitter`
* `last_update`
* `userId` (if linked)
* `identity` (if linked)
* `credential` (if linked)


#### Hash: `gliph:message:{gliphMessageId}`

* `gliphUserId` (gliph user id)
* `gliphMessageId`
* `connection_id`
* `content`
* `media`

TODO: Support media?

