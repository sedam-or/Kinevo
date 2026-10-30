use axum::{
    Router,
    body::{Body, to_bytes},
    http::{Request, StatusCode, header},
};
use kinevo_app::web_assets;
use std::error::Error;
use tower::ServiceExt;

async fn get_response(app: Router, path: &str) -> Result<axum::response::Response, Box<dyn Error>> {
    let request = Request::builder().uri(path).body(Body::empty())?;
    Ok(app.oneshot(request).await?)
}

#[tokio::test]
async fn fe_10_security_headers_exact() -> Result<(), Box<dyn Error>> {
    let app = web_assets::with_fallback(Router::new(), false);
    let response = get_response(app, "/").await?;

    assert_eq!(response.status(), StatusCode::OK);
    let headers = response.headers();
    assert_eq!(
        headers[header::CONTENT_SECURITY_POLICY],
        "default-src 'self'; script-src 'self' 'wasm-unsafe-eval'; style-src 'self'; style-src-attr 'unsafe-inline'; img-src 'self' blob: data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'"
    );
    assert_eq!(headers["referrer-policy"], "no-referrer");
    assert_eq!(headers[header::X_CONTENT_TYPE_OPTIONS], "nosniff");
    assert_eq!(headers["permissions-policy"], "");
    assert!(!headers.contains_key("strict-transport-security"));

    let secure = web_assets::with_fallback(Router::new(), true);
    let response = get_response(secure, "/").await?;
    assert_eq!(
        response.headers()["strict-transport-security"],
        "max-age=31536000; includeSubDomains"
    );
    Ok(())
}

#[tokio::test]
async fn spa_fallback_not_for_api() -> Result<(), Box<dyn Error>> {
    let app = web_assets::with_fallback(Router::new(), false);
    let shell = get_response(app.clone(), "/unknown-route").await?;
    assert_eq!(shell.status(), StatusCode::OK);
    assert_eq!(
        shell.headers()[header::CONTENT_TYPE],
        "text/html; charset=utf-8"
    );

    let api = get_response(app.clone(), "/api/v1/nope").await?;
    assert_eq!(api.status(), StatusCode::NOT_FOUND);
    assert_eq!(
        api.headers()[header::CONTENT_TYPE],
        "application/problem+json"
    );

    let asset = get_response(app, "/assets/not-found.js").await?;
    assert_eq!(asset.status(), StatusCode::NOT_FOUND);
    Ok(())
}

#[tokio::test]
async fn cache_headers_by_asset_kind() -> Result<(), Box<dyn Error>> {
    let app = web_assets::with_fallback(Router::new(), false);
    let index = get_response(app.clone(), "/").await?;
    assert_eq!(index.headers()[header::CACHE_CONTROL], "no-cache");
    let html = to_bytes(index.into_body(), usize::MAX).await?;
    let html = String::from_utf8(html.to_vec())?;
    let hashed_script = html
        .split("src=\"")
        .nth(1)
        .and_then(|value| value.split('\"').next())
        .ok_or_else(|| std::io::Error::other("built index references its hashed script"))?;

    let script = get_response(app, hashed_script).await?;
    assert_eq!(script.status(), StatusCode::OK);
    assert_eq!(
        script.headers()[header::CACHE_CONTROL],
        "public, max-age=31536000, immutable"
    );
    Ok(())
}
