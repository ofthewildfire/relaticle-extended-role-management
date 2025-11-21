@if(session('redirect_to_resource') && session('redirect_team_id'))
<script>
    // Redirect to the accessible resource
    const resource = '{{ session('redirect_to_resource') }}';
    const teamId = '{{ session('redirect_team_id') }}';
    const redirectUrl = `/app/team/${teamId}/${resource}`;
    
    console.log('Redirecting to accessible resource:', redirectUrl);
    window.location.href = redirectUrl;
</script>
@endif