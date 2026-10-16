{{--
    The landing page for logged-out visitors. `/` is inside the `guest`
    middleware group, so anyone already signed in is redirected to their home
    before this ever renders - which is why the landing can be the login screen
    itself rather than a marketing page with a login box bolted on.
--}}
@include('auth.login')
