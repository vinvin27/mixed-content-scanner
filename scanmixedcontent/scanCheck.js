
function getResult(){


jQuery( "#results" ).load(
    ajaxurl,
    {
        'action': 'mon_action',
        'urlWebsite':  jQuery('#urlWebsite').val(),
        'sbtMixedContent' : true
    },
    function(response){
            console.log(response);
            jQuery('#results').append(response);
        }
    );


    return false;
}
