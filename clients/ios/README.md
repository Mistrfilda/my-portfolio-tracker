# iOS client

Reserved for the future native Swift/SwiftUI application. There is no Xcode project or iOS build yet.

- Use the existing REST API and [OpenAPI contract](../../doc/openapi.yaml).
- Register a separate public OAuth client and use Authorization Code with S256 PKCE, system web authentication, and Keychain token storage.
- Keep Xcode/Swift Package Manager dependencies and builds separate from the desktop npm package and the root web application.
- Generate Swift API types/client code from the shared contract when implementing this client; do not reuse TypeScript implementation code.
- Add the iOS build script and a focused SwiftUI skill with the first implementation.
