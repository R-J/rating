$(document).ready(function() {
  // Break if user is not allowed to rate.
  if (gdn.definition("RatingPermission", false) != true) {
    return;
  }

  // Bind click event to RateUp/Down
  $(".RatingUp").click(function() {
    rate($(this), "up");
  });
  $(".RatingDown").click(function() {
    rate($(this), "down");
  });

  // Make ajax call for changing the score and update current score.
  function rate(el, rating) {
    // Save old score for later use and set "loading" animation.
    var elScore = el.siblings(".Rating");
    var oldScore = elScore.text();
    // Delay animation: only show if loading takes longer than 0.5 seconds.
    var timer = setTimeout(function() {
        elScore.text("");
        elScore.addClass("TinyProgress");
      },
      500
    );

    // Finally, make AJAX call.
    $.get(
      gdn.url("/plugin/rating"),
      {
        id: $(el).parent().attr('data-postid'),
        type: $(el).parent().attr('data-posttype'),
        rate: rating,
        tk: gdn.definition("TransientKey")
      }
    )
    .done(function(data) {
      // Update with current local count.
      elScore.text(parseInt(data));
    })
    .fail(function(data) {
      // Restore old value.
      elScore.text(oldScore);
    })
    .always(function() {
      // Remove timer and spinner.
      clearTimeout(timer);
      elScore.removeClass("TinyProgress");
    });
  }
});