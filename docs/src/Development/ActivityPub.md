# ActivityPub Implementation

Before starting, see [Limitations](../Limitations.md) to know more about the limit of the ActivityPub implementation.

## URLs

Those are the urls implemented by the underlying ActivityPub server: 

- instance.ltd/ap/u/{username}/followers
- instance.ltd/ap/u/{username}/following
- instance.ltd/ap/u/{username}/inbox
- instance.ltd/ap/u/{username}/outbox
- instance.ltd/profile/{username}
- instance.ltd/ap/inbox
- instance.ltd/.well-known/webfinger

where `username` can be either the current instance username, like: `janedoe`<br>
— or — the full username@instance version, like: `janedoe@instance.ltd`

## Flow and Related Code

HiveCache does not have a very complicated flow, nor all the features of a full social network.

## Following Flow

### Following Process

Let's say Alice wants to follow Bob:

1. Follow Request Initiated (`MeFollowingController`)
   - Alice's instance builds a `SendFollowMessage` that will be processed in the background
   - This message sends an HTTP call to Bob's instance (via Bob's inbox) with a Follow Activity (in ActivityPub sense)
   - Alice side: A new `Following` entity from Alice to Bob is created with status: `Pending`

2. Follow Request Received (`InboxController` on Bob's instance)
   - Bob's instance receives the request via the `InboxController`
   - It does some checking and then builds a `ReceiveFollowMessage` that will be processed in the background
   - This message sends an HTTP call to Alice's instance (via Alice's inbox) with an Accept Activity (in ActivityPub sense)
   - Bob side: A `Follower` entity from Alice to Bob is created with status: `Confirmed` (no check is performed here)

3. Accept Confirmation Received (`InboxController` on Alice's instance)
   - Alice's instance receives the request via the `InboxController`
   - It does some checking and then builds a `ReceiveAcceptMessage` that will be processed in the background
   - This message is the confirmation of the following action
   - Alice side: The `Following` entity status is updated to: `Confirmed`

## Capturing a Bookmark Flow

Capturing a bookmark creates a Note in ActivityPub sense.
These notes are formatted in a special way, so HiveCache can interpret them and other ActivityPub software can still make use of it.

### Capturing Process

Let's say Bob is capturing a bookmark:

1. Bookmark Creation (`MeBookmarkController`)
   - A POST request starts from the `MeBookmarkController`
   - The controller builds a `SendCreateNoteMessage` that will be processed in the background
   - This message sends an HTTP call to every follower of Bob (grouped by instance using the sharedInbox) with a Create Note (in ActivityPub sense)
   - Bob side: Bob has saved a bookmark to his account and the Create Note message has been sent

2. Bookmark Received (`SharedInboxController` on followers' instances)
   - Alice's instance receives the request via the `SharedInboxController`
   - It does some checking and then builds a `ReceiveCreateNoteMessage` that will be processed in the background
   - This message unbundles the individual recipient and parses the Note message to create the bookmark entity for every recipient
- Alice side: Alice's timeline shows a new bookmark from Bob

> [!IMPORTANT]
> The bookmark `mainImage` and `archive` files are references to the original Bob bookmark

## Re-capturing a Bookmark Flow

> [!NOTE]
> Re-capturing a bookmark is a special feature in HiveCache and does not use the Announce Activity available in ActivityPub.
> This is because the "favorite/repost" in ActivityPub does not match the full meaning of this action,
> as here, re-capture implies copying the bookmark `mainImage` and `archive` files.

### Re-capture Process

1. A bookmark shows in Alice's timeline
2. Alice is interested in it - they can either:
   - Let it stay in the timeline (but soon lose it as new bookmarks appear)
   - Re-capture it to save it to their own collection

Re-capture is handled the same as capturing and uses the same endpoint: `MeBookmarkController`.
The difference is that when re-capturing, the client copies the `mainImage` and `archive` files from the original bookmark
to create new file objects owned by Alice.

## Code References

Key controllers and message handlers:

- `MeFollowingController` - Initiates follow requests
- `InboxController` - Receives ActivityPub activities
- `SharedInboxController` - Receives activities for multiple recipients
- `MeBookmarkController` - Creates bookmarks and sends them to followers
- `SendFollowMessage` / `ReceiveFollowMessage` / `ReceiveAcceptMessage` - Message handlers for following flow
- `SendCreateNoteMessage` / `ReceiveCreateNoteMessage` - Message handlers for bookmark sharing
