//! Identity's refresh-cookie API re-exported from shared HTTP infrastructure.

pub use kinevo_http::cookie::{
    REFRESH_COOKIE_PLAIN, REFRESH_COOKIE_SECURE, build_refresh_cookie, clear_refresh_cookie,
    is_secure_origin, read_refresh_cookie,
};
