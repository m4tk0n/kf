<?php
// lang/en.php
declare(strict_types=1);

return [
    // app, navigation, lists
    'app.tagline' => 'Catalogue of concerts, festivals, clubs and bands.',
    'login' => 'Log in',
    'filters' => 'Filters',
    'empty' => 'Nothing found.',
    'nav.label' => 'Browse',
    'nav.home' => 'Home',
    'nav.login' => 'Log in',
    'nav.events' => 'Events',
    'nav.bands' => 'Bands',
    'nav.venues' => 'Venues',
    'search.placeholder' => 'Search…',
    'search.label' => 'Search',
    'type.1' => 'Concert',
    'type.2' => 'Festival',
    'type.3' => 'Other',

    // home
    'home.welcome' => 'Welcome, {name}.',
    'home.soon' => 'The catalogue is being prepared.',
    'user.default_name' => 'User',

    // common
    'common.save' => 'Save',
    'common.prev' => 'Previous',
    'common.next' => 'Next',
    'common.page' => 'Page {n} of {m}',

    // errors
    'error.403' => 'Access denied',
    'error.404' => 'Page not found',
    'error.429' => 'Too many requests',
    'error.generic' => 'Something went wrong',
    'error.login_hint' => 'You need to log in to access this page.',
    'error.back' => 'Back to home',

    // login
    'login.title' => 'Log in',
    'login.intro' => 'Sign in with your existing account. We do not store passwords.',
    'login.none' => 'No sign-in method is configured.',
    'login.with' => 'Log in with {provider}',

    // user menu
    'menu.add_event' => '+ Add event',
    'menu.add_band' => '+ Add band',
    'menu.add_venue' => '+ Add venue',
    'menu.my_pending' => 'My submissions',
    'menu.profile' => 'Profile',
    'menu.filters' => 'Filters',
    'menu.review' => 'To review ({n})',
    'menu.users' => 'Users',
    'menu.settings' => 'Settings',
    'menu.logout' => 'Log out',

    // roles and states
    'role.0' => 'Guest',
    'role.1' => 'User',
    'role.2' => 'Trusted user',
    'role.3' => 'Moderator',
    'role.4' => 'Administrator',
    'state.1' => 'Active',
    'state.2' => 'Suspended',
    'state.3' => 'Deleted',

    // profile
    'profile.provider' => 'Signed in with',
    'profile.role' => 'Role',
    'profile.email' => 'E-mail',
    'profile.email_none' => 'not provided',
    'profile.email_note' => 'read-only, taken from your sign-in account',
    'profile.name' => 'Display name',
    'profile.lang' => 'Language',
    'profile.lang_note' => 'Applies regardless of your browser language.',
    'profile.delete.title' => 'Delete account',
    'profile.delete.blocked' => 'This account cannot be deleted (you are the last administrator).',
    'profile.delete.warn' => 'Your account, profile and pending submissions will be deleted. Records already published in the catalogue are kept.',
    'profile.delete.fresh' => 'You will have to sign in again before deleting your account.',
    'profile.delete.confirm' => 'I understand, delete my account permanently.',
    'profile.delete.confirm_js' => 'Really delete your account? This cannot be undone.',
    'profile.delete.button' => 'Delete account',

    // filters
    'filters.intro' => 'Choose what you want to see in the lists. Saved filters can be switched on or off with one click.',
    'filters.enabled' => 'Filters enabled',
    'filters.countries' => 'Countries',
    'filters.cities' => 'Cities',
    'filters.venues' => 'Venues',
    'filters.genres' => 'Genres',
    'filters.bands' => 'Bands',
    'filters.empty' => 'Nothing here yet.',
    'filters.more' => 'Showing the first {n} items.',

    // user management
    'users.col.name' => 'Name',
    'users.col.state' => 'State',
    'users.col.approved' => 'Approved',
    'users.col.last_login' => 'Last login',
    'users.system' => 'system',
    'users.self' => 'you',
    'users.logout' => 'Log out everywhere',
    'users.delete' => 'Delete',
    'users.confirm_delete' => 'Really delete this user?',

    // settings and overviews
    'settings.db' => 'Database',
    'settings.files' => 'Files (inodes)',
    'settings.pending' => 'Submissions to review',
    'settings.mail' => 'E-mail queue',
    'settings.cron' => 'Last cron run',
    'settings.never' => 'never',
    'settings.users' => 'Users by role',
    'settings.log' => 'Security log (last 30 lines)',
    'settings.log_empty' => 'The log is empty.',

    // flash messages (?m=code)
    'flash.saved' => 'Saved.',
    'flash.invalid' => 'Invalid input.',
    'flash.denied' => 'This action is not allowed.',
    'flash.last_admin' => 'The last administrator cannot be changed or deleted.',
    'flash.confirm_needed' => 'Confirmation is required to delete the account.',
    'flash.filters_saved' => 'Filters saved.',
    'flash.user_deleted' => 'User deleted.',
    'flash.sessions_revoked' => 'The user has been logged out of all devices.',
    'flash.logged_out' => 'You have been logged out.',
    'flash.account_deleted' => 'Your account has been deleted.',
    'flash.oauth_failed' => 'Sign-in failed, please try again.',
    'flash.account_inactive' => 'This account is not active.',

    // stránkování a kalendář (katalog)
    'pager.label' => 'Pagination',
    'pager.first' => 'First',
    'pager.prev' => 'Previous',
    'pager.next' => 'Next',
    'pager.last' => 'Last',
    'pager.prev_month' => 'Month back',
    'pager.prev_day' => 'Previous day with events',
    'pager.next_day' => 'Next day with events',
    'pager.next_month' => 'Month forward',
    'months' => ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
    'count.events' => ['one' => '{n} event', 'other' => '{n} events'],
    'count.bands' => ['one' => '{n} band', 'other' => '{n} bands'],
    'count.venues' => ['one' => '{n} venue', 'other' => '{n} venues'],
];
