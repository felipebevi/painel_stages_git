$(document).ready(function() {
    const baseUrl = '/painel_stages_git/src/index.php'; // Base URL para as requisições

    // Função para lidar com respostas JSON de forma segura
    function safeJsonParse(responseText) {
        try {
            return JSON.parse(responseText);
        } catch (e) {
            console.error('JSON parse error: ', e, 'Response Text:', responseText);
            return null;
        }
    }

    // Fetch environments
    $.get(baseUrl, { path: 'environments' }, function(data) {
        const environments = safeJsonParse(data);
        if (environments) {
            environments.forEach(function(env) {
                $('#environmentSelect').append(new Option(env.name, env.name));
            });
        } else {
            console.log('Failed to load environments');
        }
    }).fail(function(jqXHR, textStatus, errorThrown) {
        console.error('AJAX error: ', textStatus, ' : ', errorThrown);
    });

    // Fetch branches
    $.get(baseUrl, { path: 'branches' }, function(data) {
        const branches = safeJsonParse(data);
        if (branches) {
            branches.forEach(function(branch) {
                $('#branchSelect').append(new Option(branch, branch));
            });
        } else {
            console.log('Failed to load branches');
        }
    }).fail(function(jqXHR, textStatus, errorThrown) {
        console.error('AJAX error: ', textStatus, ' : ', errorThrown);
    });

    // Edit ENV button click
    $('#editEnvBtn').click(function() {
        const environment = $('#environmentSelect').val();
        if (!environment) {
            console.log('Please select an environment first');
            return;
        }

        $.get(baseUrl, { path: 'environment', name: environment }, function(data) {
            $('#envTextarea').val(data);
            $('#envModal').modal('show');
        }).fail(function(jqXHR, textStatus, errorThrown) {
            console.error('AJAX error: ', textStatus, ' : ', errorThrown);
        });
    });

    // Save ENV changes
    $('#saveEnvBtn').click(function() {
        const environment = $('#environmentSelect').val();
        const envContent = $('#envTextarea').val();
        $.ajax({
            url: baseUrl + '?path=environment&name=' + environment,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ content: envContent }),
            success: function(response) {
                console.log('ENV updated successfully');
                $('#envModal').modal('hide');
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('Failed to update ENV: ', textStatus, ' : ', errorThrown);
            }
        });
    });

    // Deploy button click
    $('#deployBtn').click(function() {
        const environment = $('#environmentSelect').val();
        const branch = $('#branchSelect').val();
        const envContent = $('#envTextarea').val();

        if (!environment || !branch) {
            console.log('Please select both environment and branch');
            return;
        }

        const data = {
            environment: environment,
            branch: branch,
            envContent: envContent
        };

        $.ajax({
            url: baseUrl + '?path=deploy',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(data),
            success: function(response) {
                const result = safeJsonParse(response);
                if (result && result.status === 'success') {
                    console.log('Deployment successful');
                } else {
                    console.log('Deployment failed');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('Deployment failed: ', textStatus, ' : ', errorThrown);
            }
        });
    });
});
