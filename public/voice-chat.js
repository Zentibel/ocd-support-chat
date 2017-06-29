$(document).ready(function() {
    var inVoiceChat = false;
    $('#voice-chat-button').click(function(event) {
        event.preventDefault();
        if (!inVoiceChat) {
            $('body').append('<iframe id="voice-chat-frame" src="https://instachit.com/ochatd" style="width: 1px; height: 1px;"></iframe>');
            //$('body').append('<iframe id="voice-chat-frame" src="https://www.talk.gg/ochatd" style="width: 1px; height: 1px;"></iframe>');
            //$('#voice-chat-frame').hide();
            $('#voice-chat-button').text('Leave Voice Chat');
            inVoiceChat = true;
            showToast('You are now in the voice chat.');
        } else {
            $('#voice-chat-frame').remove();
            $('#voice-chat-button').text('Join Voice Chat');
            inVoiceChat = false;
            showToast('You have left the voice chat.');
        }
    });
});
