Attachment caching with MidCOM 3
================================

MidCOM 3 supports attachment caching. Basically it means that attachments can be served without MidCOM 3's intervention.

Usage: Add following lines to the default configuration

    enable_attachment_cache: true
    attachment_handler: midgardmvc_core_helpers_attachment
    attachment_cache_url: http://myimageserver.com/blobs/
    attachment_cache_directory: /path/to/the/directory/accessible/by/webserver/blobs/

Basically attachments can be served from totally different server.

If you want to serve your attachments from some content delivery network it will require you to write your own attachment handler. You need to implement interface that has been defined in MidCOM 3's default `attachment_handler`.

Interface requires that you implement functions for putting and removing attachments and some function that returns the URL of the attachment.
