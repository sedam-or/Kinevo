use crate::app::refresh::Client;

/// Produces a short, content-free label without retaining a raw user-agent string.
#[must_use]
pub fn label(user_agent: Option<&str>, client: Client) -> &'static str {
    if client == Client::Android {
        return "Nivaras Android";
    }
    let Some(user_agent) = user_agent else {
        return "Unknown device";
    };
    let browser = if user_agent.contains("Firefox/") {
        "Firefox"
    } else if user_agent.contains("Edg/") {
        "Edge"
    } else if user_agent.contains("Chrome/") || user_agent.contains("CriOS/") {
        "Chrome"
    } else if user_agent.contains("Safari/") {
        "Safari"
    } else {
        return "Unknown device";
    };
    let platform = if user_agent.contains("Android") {
        "Android"
    } else if user_agent.contains("iPhone") || user_agent.contains("iPad") {
        "iOS"
    } else if user_agent.contains("Windows") {
        "Windows"
    } else if user_agent.contains("Macintosh") {
        "macOS"
    } else if user_agent.contains("Linux") {
        "Linux"
    } else {
        return "Unknown device";
    };
    match (browser, platform) {
        ("Firefox", "Linux") => "Firefox on Linux",
        ("Firefox", "Windows") => "Firefox on Windows",
        ("Firefox", "macOS") => "Firefox on macOS",
        ("Chrome", "Android") => "Chrome on Android",
        ("Chrome", "Linux") => "Chrome on Linux",
        ("Chrome", "Windows") => "Chrome on Windows",
        ("Chrome", "macOS") => "Chrome on macOS",
        ("Safari", "iOS") => "Safari on iOS",
        ("Safari", "macOS") => "Safari on macOS",
        ("Edge", "Windows") => "Edge on Windows",
        ("Edge", "Linux") => "Edge on Linux",
        _ => "Unknown device",
    }
}
