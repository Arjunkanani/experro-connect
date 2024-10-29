require(['jquery','mage/url'], function($,urlBuilder) {
$(document).ready(function() {



let connections = []; // Array to store connection data

document.getElementById('connect-experro').addEventListener('click', function(event) {
    event.preventDefault();
    document.getElementById('connection-info').style.display = 'none';
    document.getElementById('connection-form').style.display = 'block';
});

document.getElementById('cancel').addEventListener('click', function() {
    document.getElementById('experro-connection-form').reset();
    document.getElementById('connection-info').style.display = 'block';
    document.getElementById('connection-form').style.display = 'none';
    clearErrors(); // Clear error messages
});

document.getElementById('next').addEventListener('click', function() {
clearErrors(); // Clear previous error messages
const activeStep = document.querySelector('.step.active');

if (activeStep.textContent.includes('Store Details')) {
    // Validate Step 1 inputs
    const connectionName = document.getElementById('connection-name').value;
    const experroToken = document.getElementById('experro-token').value;
    let isValid = true;

    if (!connectionName) {
        document.getElementById('error-connection-name').textContent = 'Connection Name is required.';
        isValid = false;
    }

    if (!experroToken) {
        document.getElementById('error-experro-token').textContent = 'Experro Token is required.';
        isValid = false;
    }

    if (isValid) {
        var baseUrl = window.location.protocol + '//' + window.location.host + '/'; 
        $.ajax({
            url: baseUrl+'admin/checkprerequisites/experro/verifytoken',
            type: 'POST',
            dataType: 'json',
            data: {
                experroToken: experroToken,
                connectionName: connectionName
            },
            success: function(response) {
                if (response.success) {
                    // Proceed to Step 2 if verification is successful
                    document.getElementById('step-1').classList.add('hidden');
                    document.getElementById('step-2').classList.remove('hidden');
                    activeStep.classList.remove('active');
                    activeStep.nextElementSibling.classList.add('active');

                     // Change button text to "Complete" when on Step 2
                    document.getElementById('next').textContent = 'Complete';
                    var databaseTableId = response.id;

                    // Fetch system integration token details
                    $.ajax({
                        url: baseUrl+'admin/checkprerequisites/experro/getTokenDetails',
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            form_key: window.FORM_KEY
                        },
                        success: function(tokenResponse) {
                            if (tokenResponse.success) {
                                // Populate Step 2 fields with token details
                                document.getElementById('client-id').value = tokenResponse.clientId;
                                document.getElementById('client-secret').value = tokenResponse.clientSecret;
                                document.getElementById('access-token').value = tokenResponse.accessToken;
                                document.getElementById('access-token-secret').value = tokenResponse.accessTokenSecret;

                                const inputFields = document.querySelectorAll('form#experro-connection-form div#step-2 input');
        
                                    inputFields.forEach((inputField) => {
                                        const text = inputField.value;

                                        const wrapper = document.createElement('div');
                                        inputField.parentNode.insertBefore(wrapper, inputField);
                                        wrapper.appendChild(inputField);

                                        const copyIcon = document.createElement('div');
                                        copyIcon.innerHTML = `
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" fill="#9f9f9f" style="cursor: pointer;">
                                                <path d="M64 464l224 0c8.8 0 16-7.2 16-16l0-64 48 0 0 64c0 35.3-28.7 64-64 64L64 512c-35.3 0-64-28.7-64-64L0 224c0-35.3 28.7-64 64-64l64 0 0 48-64 0c-8.8 0-16 7.2-16 16l0 224c0 8.8 7.2 16 16 16zM224 304l224 0c8.8 0 16-7.2 16-16l0-224c0-8.8-7.2-16-16-16L224 48c-8.8 0-16 7.2-16 16l0 224c0 8.8 7.2 16 16 16zm-64-16l0-224c0-35.3 28.7-64 64-64L448 0c35.3 0 64 28.7 64 64l0 224c0 35.3-28.7 64-64 64l-224 0c-35.3 0-64-28.7-64-64z"/>
                                            </svg>`;
                                        copyIcon.addEventListener('click', () => {
                                            if (navigator.clipboard && navigator.clipboard.writeText) {
                                                navigator.clipboard.writeText(text)
                                                    .then(() => alert('Copied to clipboard!'))
                                                    .catch(err => console.error('Failed to copy text: ', err));
                                            } else {
                                                const textarea = document.createElement('textarea');
                                                textarea.value = text;
                                                document.body.appendChild(textarea);
                                                textarea.select();
                                                try {
                                                    document.execCommand('copy');
                                                    //alert('Copied to clipboard!');
                                                } catch (err) {
                                                    console.error('Fallback: Could not copy text', err);
                                                }
                                                document.body.removeChild(textarea);
                                            }
                                        });

                                        wrapper.appendChild(copyIcon);
                                    });

                                var baseUrl = window.location.protocol + '//' + window.location.host + '/'; 
                                $.ajax({
                                    url: baseUrl+'admin/checkprerequisites/experro/savingdata',
                                    type: 'POST',
                                    dataType: 'json',
                                    data: {
                                        form_key: window.FORM_KEY,
                                        client_id: tokenResponse.clientId,
                                        client_secret:tokenResponse.clientSecret,
                                        access_token:tokenResponse.accessToken,
                                        access_token_secret:tokenResponse.accessTokenSecret,
                                        databaseTableId : databaseTableId
                                    },
                                    success: function(response) {
                                        if (response.success) {
                                            //location.reload();

                                        } else {
                                            // Show error message from the controller
                                            document.getElementById('saving-error').textContent = response.message;
                                        }
                                    },
                                    error: function() {
                                        // Handle any errors from the AJAX request
                                        document.getElementById('saving-error').textContent = 'An error occurred during saving data.';
                                    }
                                });

                            } else {
                                alert(tokenResponse.message);
                            }
                        },
                        error: function() {
                            alert('Error fetching token details.');
                        }
                    });
                } else {
                    // Show error message from the controller
                    document.getElementById('error-experro-token').textContent = response.message;
                }
            },
            error: function() {
                // Handle any errors from the AJAX request
                document.getElementById('error-experro-token').textContent = 'An error occurred during token verification.';
            }
        });
    }
} else if (activeStep.textContent.includes('Token Details')) {
    // Validate Step 2 inputs
    const clientId = document.getElementById('client-id').value;
    const clientSecret = document.getElementById('client-secret').value;
    const accessToken = document.getElementById('access-token').value;
    const accessTokenSecret = document.getElementById('access-token-secret').value;
    let isValid = true;

    if (!clientId) {
        document.getElementById('error-client-id').textContent = 'Client ID is required.';
        isValid = false;
    }

    if (!clientSecret) {
        document.getElementById('error-client-secret').textContent = 'Client Secret is required.';
        isValid = false;
    }

    if (!accessToken) {
        document.getElementById('error-access-token').textContent = 'Access Token is required.';
        isValid = false;
    }

    if (!accessTokenSecret) {
        document.getElementById('error-access-token-secret').textContent = 'Access Token Secret is required.';
        isValid = false;
    }

    if (isValid) {

        // Show completion section
        showCompletionSection();
    }
}
});

});
});



function showCompletionSection() {
    // Hide the connection form and show the completion info

    window.location.reload(true);
    
    document.getElementById('connection-form').style.display = 'none';
    document.getElementById('completion-info').style.display = 'block';

    

}

function clearErrors() {
    document.getElementById('error-connection-name').textContent = '';
    document.getElementById('error-experro-token').textContent = '';
    document.getElementById('error-client-id').textContent = '';
    document.getElementById('error-client-secret').textContent = '';
    document.getElementById('error-access-token').textContent = '';  // Clear Access Token error
    document.getElementById('error-access-token-secret').textContent = '';  // Clear Access Token Secret error
}

require(['jquery'], function($) {
    $(document).ready(function() {
        document.getElementById('connect-experro-again').addEventListener('click', function() {
            // Reset the form and connections
            connections = [];
            document.getElementById('completion-info').style.display = 'none';
            document.getElementById('connection-info').style.display = 'block';
            document.getElementById('connect-message').style.display = 'none'; // Hide connect message
        });
    });
});


require(['jquery'], function($) {
    $(document).ready(function() {
        // Open the popup and populate the data
        $('.view-details').on('click', function(event) {
            event.preventDefault(); // Prevent the default action
            var rowId = $(this).data('connection-id');
            console.log(rowId);
            var baseUrl = window.location.protocol + '//' + window.location.host + '/'; 
            $.ajax({
                url: baseUrl+'admin/checkprerequisites/experro/viewdetails',
                type: 'POST',
                dataType: 'json',
                data: {form_key: window.FORM_KEY,rowId:rowId},
                success: function(response) {
                    if (response.success) {

                         $('#popup-connection-name').text(response.data.connection_name);
                        $('#popup-experro-token').text(response.data.experro_token);
                        $('#popup-client-id').text(response.data.client_id);
                        $('#popup-client-secret').text(response.data.client_secret);
                        $('#popup-access-token').text(response.data.access_token);
                        $('#popup-access-token-secret').text(response.data.access_token_secret);
                        
                        // Show the popup/modal (you can trigger modal show here)
                        $('#details-popup').fadeIn(); // Adjust this based on your modal implementation

                    } else {
                        // Show error message from the controller
                        document.getElementById('saving-error').textContent = response.message;
                    }
                },
                error: function() {
                    // Handle any errors from the AJAX request
                    document.getElementById('saving-error').textContent = 'An error occurred during saving data.';
                }
            });
        });

        // Close the popup
        $('#close-popup').on('click', function() {
            $('#details-popup').fadeOut();
        });

        // Close the popup when clicking outside the popup content
        $(document).on('click', function(event) {
            if ($(event.target).is('.popup-overlay')) {
                $('#details-popup').fadeOut();
            }
        });
    });
});



// Delete ICON

require(['jquery'], function($) {
    $(document).ready(function() {
        // Open the popup and populate the data
        $('.delete').on('click', function(event) {
            event.preventDefault(); // Prevent the default action
            var rowId = $(this).data('connection-id');
            console.log(rowId);
            var baseUrl = window.location.protocol + '//' + window.location.host + '/'; 
            $.ajax({
                url: baseUrl+'admin/checkprerequisites/experro/delete',
                type: 'POST',
                dataType: 'json',
                data: {form_key: window.FORM_KEY,rowId:rowId},
                success: function(response) {
                    if (response.success) {
                        window.location.reload(true);
                    } else {
                        // Show error message from the controller
                        document.getElementById('saving-error').textContent = response.message;
                    }
                },
                error: function() {
                    // Handle any errors from the AJAX request
                    document.getElementById('saving-error').textContent = 'An error occurred during saving data.';
                }
            });
        });
    });
});