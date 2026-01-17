# Activity Pub

## Limited implementation

The goal of this implementation is not to be fully compatible with all the fediverse but mostly to allow multiple instances of BookmarkHive to communicate together.

BookmarkHive does not:

 - Allow replies, threads and conversations
 - Allow favorites and bookmarking (in the ActivityPub sense)
 - Allow accepting/rejecting following request
 - Show followers list
 - Send any notification

## Flow and related code

BookmarkHive does not have a very complicated flow, nor all the feature of a full social network.

### Following

In BookmarkHive implementation, there is not much of a follower concept, followers does not show in the official client.
That's why there is no accept/reject concept too. They are not notifications about a new follower either. This may evolve in the future.

#### Flow

A follow request start from the MeFollowingController (let's say Alice will follow Bob).
The controller will build a SendFollowMessage that will be processed in the background.
This message will send an HTTP call to Bob instance (via Bob inbox) with a Follow Activity (in ActivityPub sense).
> Alice side: For now we have a new Following entity from Alice to Bob with status: Pending.

From here, Bob instance receive the request via the InboxController.
It will do some checking and then build a ReceiveFollowMessage that will be processed in the background.
This message will send an HTTP call to Alice instance (via Alice inbox) with a Accept Activity (in ActivityPub sense).
> Bob side: Now we have the Follower entity from Alice to Bob with status: Confirmed (we don't do a check here).

Ultimately, Alice instance receive the request via the InboxController.
It will do some checking and then build a ReceiveAcceptMessage that will be processed in the background.
This message is the confirmation of the following action.
> Alice side: Now we have the Following entity status: Confirmed.

### Capturing a bookmark

Capturing a bookmark will be Creating a Note in ActivityPub sense.
Those notes are formated in a special way, so BookmarkHive can interpret them and other ActivityPub software can still make use of it.

#### Flow 

A post request start from the MeBookmarkController (let's say Bob is capturing).
The controller will build a SendCreateNoteMessage that will be processed in the background.
This message will send an HTTP call to every follower of Bob (grouped by instance using the sharedInbox) with a Create Note (in ActivityPub sense).
> Bob side: For now Bob has saved a bookmark to his account and the Create Note message has been sent.

From here, Alice instance receive the request via the SharedInboxController.
It will do some checking and then build a ReceiveCreateNoteMessage that will be processed in the background.
This message will unbundle the individual recipient and parse the Note message to create the bookmark entity for every recipient.
> Alice side: Alice timeline shows a new bookmark from Bob. Important note: the bookmark mainImage and archive files are reference to original Bob bookmark.

### Re-capture a bookmark

Re-capture a bookmark is a special thing in BookmarkHive and does not use the Announce Activity available in ActivityPub.
This is because the "favorite/repost" in ActivityPub does not match the full meaning of this action,
as here, recapture will imply copying the bookmark mainImage and archive files.

#### Flow 

A bookmark shows in Alice timeline. They are interested about it, either they can let it here, 
but soon lose it in the timeline, or they could re-capture it.

Re-capture is the same as Capturing and is handled by the client using the same endpoint: MeBookmarkController.
