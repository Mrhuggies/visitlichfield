/*
 * Moves auth tokens out of the URL and into request headers.
 *
 * Pages historically called  .../api/admin/drivers.php?token=XYZ  which puts the token in
 * server access logs, browser history and Referer headers. This wrapper strips `token=`
 * from calls to the authenticated API endpoints and sends it as X-Admin-Token /
 * X-Driver-Token instead (the PHP side prefers the header). Load it before other scripts.
 *
 * verify-email.php and reset-password.php use a one-time `token` that is NOT an auth token,
 * so they are left alone.
 */
(function () {
    var nativeFetch = window.fetch;
    var untouched = /\/(verify-email|reset-password)\.php/;

    window.fetch = function (input, init) {
        try {
            if (typeof input === 'string' && input.indexOf('/api/') !== -1 && !untouched.test(input)) {
                var url = new URL(input, window.location.href);
                var token = url.searchParams.get('token');
                if (token) {
                    url.searchParams.delete('token');
                    var headers = new Headers((init && init.headers) || {});
                    headers.set(url.pathname.indexOf('/api/admin/') !== -1 ? 'X-Admin-Token' : 'X-Driver-Token', token);
                    init = Object.assign({}, init, { headers: headers });
                    input = url.toString();
                }
            }
        } catch (e) { /* fall through to the original call */ }
        return nativeFetch.call(this, input, init);
    };
})();
