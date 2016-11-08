/*

bind click event to up/down votes
update score
reorder? no, reload needed
 */

$( document ).ready(function() {
    $( ".RatingUp" ).click(function() {
        rate( $( this ), "up" );
    });
    $( ".RatingDown" ).click(function() {
        rate( $( this ), "down" );
    });

    function rate(el, rating) {
        var id = $( el ).attr( "DiscussionID" );
        if ( id ) {
            var type = 'discussion';
        } else {
            id = $( el ).attr( "CommentID" );
            var type = 'comment';
        }

        $.get(
            gdn.url( "/plugin/rating" ),
            {
                id: id,
                type: type,
                rating: rating,
                tk: gdn.definition( "TransientKey" )
            }
        )
        .done(function( data ) {
            el.siblings( ".Rating" ).text(parseInt(data));
        });
    }
});