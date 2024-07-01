$(document).ready(function() {
    const baseUrl = '/painel_stages_git/src/index.php'; // Base URL para as requisições

    // Fetch environments
    $.get(baseUrl, { path: 'environments' }, function(data) {
        const environments = JSON.parse(data);
        environments.forEach(function(env) {
            $('#environmentSelect').append(new Option(env.name, env.name));
        });
    });

    // Fetch branches
    $.get(baseUrl, { path: 'branches' }, function(data) {
        const branches = JSON.parse(data);
        branches.forEach(function(branch) {
            $('#branchSelect').append(new Option(branch, branch));
        });
    });

    // Edit ENV button click
    $('#editEnvBtn').click(function() {
        const environment = $('#environmentSelect').val();
        if (!environment) {
            alert('Please select an environment first');
            return;
        }

        $.get(baseUrl, { path: 'environment', name: environment }, function(data) {
            $('#envTextarea').val(data);
            $('#envModal').modal('show');
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
                alert('ENV updated successfully');
                $('#envModal').modal('hide');
            },
            error: function() {
                alert('Failed to update ENV');
            }
        });
    });

    // Deploy button click
    $('#deployBtn').click(function() {
        const environment = $('#environmentSelect').val();
        const branch = $('#branchSelect').val();
        const envContent = $('#envTextarea').val();

        if (!environment || !branch) {
            alert('Please select both environment and branch');
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
                alert('Deployment successful');
            },
            error: function() {
                alert('Deployment failed');
            }
        });
    });
});
