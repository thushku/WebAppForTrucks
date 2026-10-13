# Offline-first B4A/B4i field app with background GPS, photo evidence and a Laravel/MySQL API (as of Oct 2026)

Research notes for the truck crew app (drivers, co-drivers, helpers; India and UAE; low-end to latest Android; B4A first, B4i later; Laravel/MySQL REST API).

**Method and limits.** Research ran on 2026-10-13. Several primary hosts could not be fetched from this environment because the egress proxy blocked them: www.b4x.com, dontkillmyapp.com, support.google.com, developers.google.com, laravel.com, blog.google, mapsplatform.google.com, fmcsa.dot.gov and ecfr.gov. For those sources, findings come from search-engine summaries of the named pages, and the page URL is cited. These are marked "(via search summary)". Treat them as slightly lower confidence than directly fetched pages (developer.android.com, developer.apple.com). The session's web-search budget ran out before the ELD/hours-of-service, perceptual-hash, OCR-resolution and Essentials-India price questions could be searched. Those items are in Gaps or are labelled as unverified prior knowledge in Inferences. Facts are kept separate from recommendations throughout.

---

## Q1. Android background location in 2026: foreground service types, background permission, background-start limits, Play policy, battery and OEM killers, intervals, FLP vs GPS

### Takeaway
The reliable 2026 pattern is a user-started foreground service with `foregroundServiceType="location"` and `FOREGROUND_SERVICE_LOCATION`, started while an Activity is visible (when the driver taps "Start duty"). It should use FusedLocationProviderClient with moderate intervals and batching. Add `ACCESS_BACKGROUND_LOCATION` only if the service must restart itself after reboot or app update. That permission triggers a Play Console declaration and a review video. On top of this, the app must guide each user through battery-optimisation and OEM auto-start settings, because Xiaomi, Oppo, Vivo, Realme and Samsung still kill background apps. From 31 Aug 2026, Play updates must target API 36.

### Cited Findings

**Google Play target API level (2026)**
- From **31 Aug 2026**, new apps and app updates on Google Play must target **Android 16 (API 36)**. Existing apps must target **API 35 or higher** to stay visible to new users on devices running newer Android versions. Developers can request an extension in Play Console, which moves the deadline to **1 Nov 2026**. — [Android Developers: Target API level requirements](https://developer.android.com/google/play/requirements/target-sdk) (via search summary); corroborated by [appsops.store, Aug 2026](https://appsops.store/news/google-play-august-2026-deadlines-api36-ai-policy). A MIT App Inventor community post gives 30 Aug 2026; Google's page says 31 Aug. — [community.appinventor.mit.edu](https://community.appinventor.mit.edu/t/issue-details-your-app-must-target-android-16-api-level-36-or-higher/175021)

**Foreground service (FGS) type "location" (Android 14+)**
- Required declarations: `android:foregroundServiceType="location"`, the permission `FOREGROUND_SERVICE_LOCATION` (in addition to `FOREGROUND_SERVICE`), and `FOREGROUND_SERVICE_TYPE_LOCATION` passed to `startForeground()`. Runtime prerequisite: "The user must have enabled location services and the app must be granted at least one of … `ACCESS_COARSE_LOCATION`, `ACCESS_FINE_LOCATION`." — [Android Developers: Foreground service types](https://developer.android.com/develop/background-work/services/fgs/service-types)
- "You cannot create a `location` foreground service while your app is in the background, unless you've been granted the `ACCESS_BACKGROUND_LOCATION` runtime permission." — [same page](https://developer.android.com/develop/background-work/services/fgs/service-types)
- From Android 14 (API 34) you "must declare an appropriate service type for each foreground service" in the manifest and request the matching FGS permission. — [same page](https://developer.android.com/develop/background-work/services/fgs/service-types)
- `shortService` FGS runs for about 3 minutes and cannot be sticky. — [same page](https://developer.android.com/develop/background-work/services/fgs/service-types)

**Starting an FGS from the background (Android 12–16)**
- Apps targeting Android 12+ "can't start foreground services while the app is running in the background, except for a few special cases"; otherwise `ForegroundServiceStartNotAllowedException` is thrown. — [Android Developers: Restrictions on starting a FGS from the background](https://developer.android.com/develop/background-work/services/fgs/restrictions-bg-start)
- Android 14+: a FGS that needs a while-in-use permission (location, camera, microphone) "can't create it from the background, even if it qualifies for a general exemption". You "must call `Context.startForegroundService()` … while your app has a visible activity, unless the service falls into one of the defined exemptions." Missing permission at creation throws `SecurityException`. — [same page](https://developer.android.com/develop/background-work/services/fgs/restrictions-bg-start)
- Location-specific rule: if the service declares the `location` type and the app holds `ACCESS_BACKGROUND_LOCATION`, the service "can access location information all the time, even when the app runs in the background." — [same page](https://developer.android.com/develop/background-work/services/fgs/restrictions-bg-start)
- General background-start exemptions include: user-visible state; high-priority FCM; user interaction with a notification or widget; an exact alarm for a user-requested action; geofencing or activity-recognition transitions; `ACTION_BOOT_COMPLETED` / `ACTION_LOCKED_BOOT_COMPLETED` / `ACTION_MY_PACKAGE_REPLACED`; `ACTION_TIMEZONE_CHANGED` / `ACTION_TIME_CHANGED`; and "The user turns off battery optimizations for your app." — [same page](https://developer.android.com/develop/background-work/services/fgs/restrictions-bg-start)
- Diagnostic log line when location is withheld: "Foreground service started from background can not have location/camera/microphone access". — [same page](https://developer.android.com/develop/background-work/services/fgs/restrictions-bg-start)
- Android 15 (targeting API 35): `BOOT_COMPLETED` receivers may not launch `dataSync`, `camera`, `mediaPlayback`, `phoneCall`, `mediaProjection` or `microphone` FGS. `location` is **not** on that list. `dataSync` FGS are limited to "a total of 6 hours in a 24-hour period", after which `onTimeout` fires. Edge-to-edge is on by default for apps targeting 35. — [Android Developers: Android 15 behavior changes](https://developer.android.com/about/versions/15/behavior-changes-15)
- Android 16 (targeting API 36): the edge-to-edge opt-out (`windowOptOutEdgeToEdgeEnforcement`) is disabled. Predictive back is on by default, so "`onBackPressed` is not called and `KeyEvent.KEYCODE_BACK` is not dispatched anymore" unless the app opts out temporarily. Orientation and resizability locks are ignored on screens ≥600dp. The Android 16 targeting page lists no new FGS or location rules. — [Android Developers: Android 16 behavior changes](https://developer.android.com/about/versions/16/behavior-changes-16)

**ACCESS_BACKGROUND_LOCATION request flow**
- On Android 11 (API 30)+, "the system dialog doesn't include the **Allow all the time** option. Instead, users must enable background location on a settings page". The app should show educational UI that uses `getBackgroundPermissionOptionLabel()` (a localised label) and must let users decline and keep using the app. — [Android Developers: Request background location](https://developer.android.com/develop/sensors-and-location/location/permissions/background)
- If the user grants only approximate location in the foreground, background access is approximate too. — [same page](https://developer.android.com/develop/sensors-and-location/location/permissions/background)

**Google Play background-location policy and declaration**
- Background location is allowed only for a core feature. Apps must pass the Play Console permission-declaration process, and Google may reject requests "without compelling justification". Location may not be used solely for ads or analytics. Foreground-service location under "while in use" permission is allowed when "initiated as a continuation of an in-app user-initiated action, and is terminated immediately after the intended use case is completed." — [Play Console Help: Understanding location in the background permissions](https://support.google.com/googleplay/android-developer/answer/9799150?hl=en) (via search summary)
- Declaration form details from older guides (2020–2021): short text answers plus a video link showing the prominent in-app disclosure, the feature, and how the user enables it. The prominent disclosure must appear before any location permission request. — [Android Developers Blog, Nov 2020](https://android-developers.googleblog.com/2020/11/tips-for-getting-your-app-approved-for-background-location-access.html); [Roam.ai guide](https://www.roam.ai/blog/best-practices-for-google-play-store-background-location-approval) (dated; verify against the current form)

**Battery optimisation / Doze**
- Doze "Suspends network access", "Ignores wake locks", defers standard alarms, and stops JobScheduler and WorkManager. `setExactAndAllowWhileIdle()` fires at most once per 9 minutes per app. — [Android Developers: Doze and App Standby](https://developer.android.com/training/monitoring-device-state/doze-standby)
- Apps with an acceptable use case may use `ACTION_REQUEST_IGNORE_BATTERY_OPTIMIZATIONS` for a direct prompt. Other apps should send users to `ACTION_IGNORE_BATTERY_OPTIMIZATION_SETTINGS`. Apps can check status with `isIgnoringBatteryOptimizations()`. "Google Play policies prohibit apps from requesting direct exemption … unless the core function of the app is adversely affected." Listed acceptable cases include "Task automation" and "Safety" apps. Fleet tracking is not named explicitly. — [same page](https://developer.android.com/training/monitoring-device-state/doze-standby)
- An app that is partially exempt "can use the network and hold partial wake locks during Doze and App Standby". — [same page](https://developer.android.com/training/monitoring-device-state/doze-standby)

**OEM background killers**
- Samsung (Android 9+): apps idle for more than 3 days can be force-stopped and blocked from the background. Locking the app in Recents helps. Settings may reset after firmware updates. — [Gigazine summary of dontkillmyapp.com, Dec 2023](https://gigazine.net/gsc_news/en/20231210-dont-kill-my-app)
- Xiaomi/MIUI (now HyperOS) is "among the most problematic". Fix: battery saver "No restrictions" plus Autostart enabled in the Security app. Oppo/Realme ColorOS: allow background activity and unrestricted battery. Vivo: enable "high background power consumption", "Allow background activity" and Autostart. Ultra battery-saver modes on Xiaomi and Vivo kill apps. — [Biolovision help: Keep background services alive by brand](https://help.biolovision.net/Keep_Background_Services_Alive_on_Android_(by_Brand)); [Gigazine](https://gigazine.net/gsc_news/en/20231210-dont-kill-my-app)
- dontkillmyapp.com itself could not be fetched. Its per-brand pages remain the canonical checklist (https://dontkillmyapp.com/).

**Intervals, batching, FLP priorities**
- `PRIORITY_HIGH_ACCURACY` "may cause significant battery drain". `PRIORITY_BALANCED_POWER_ACCURACY` "Very rarely uses GPS". Google recommends balanced or low power for most apps and high accuracy only for foreground real-time needs. — [Android Developers: Optimize location for battery](https://developer.android.com/develop/sensors-and-location/location/battery)
- `setIntervalMillis()`: "Pass the largest possible value". `setMaxUpdateDelayMillis()`: "typically … several times larger than the interval … multiple location updates may be delivered in batches." — [same page](https://developer.android.com/develop/sensors-and-location/location/battery)
- Without an FGS (Android 8+), "Background location gathering is throttled and location is computed, and delivered only a few times an hour." — [same page](https://developer.android.com/develop/sensors-and-location/location/battery)

### Inferences
- **Recommended Android architecture.**
  1. "Start Duty" button in a visible Activity starts the location FGS with a persistent notification showing duty status, last fix time and queued count.
  2. FusedLocationProviderClient at `PRIORITY_HIGH_ACCURACY` while moving. Interval 30–60 s, minimum distance 50–100 m, `setMaxUpdateDelayMillis` 5–10 min (batching).
  3. When stationary for more than 5 minutes, drop to `BALANCED` at 5 min.
  4. Write every fix to SQLite immediately. Upload in batches every 5–15 minutes, or when the batch reaches N points, or when connectivity returns.

  This gives roughly 60–120 points per hour while driving. Each point is about 40–60 bytes as compact JSON or CSV, gzipped, so a 12-hour shift is under 100 KB of payload. (Recommendation; the interval values are engineering judgement, not from a cited source.)
- Do **not** use the `dataSync` FGS type for uploads (6 h/day cap on API 35+). Do the uploads inside the location FGS's process, or as short work when the app is foreground or charging.
- Because `location` FGS is not blocked from `BOOT_COMPLETED` on Android 15, auto-restart after reboot is possible, but only if `ACCESS_BACKGROUND_LOCATION` was granted. Without it, show a high-priority notification after boot: "Tap to resume duty tracking". Tapping a notification is a valid while-in-use exemption.
- For Play review, the declared core feature is "duty-time crew location tracking for the fleet operator". Prepare: (a) a prominent-disclosure screen before any location prompt; (b) a privacy policy that covers employee tracking; (c) a 30–60 s video. If the auto-resume-after-reboot feature is not essential, skip `ACCESS_BACKGROUND_LOCATION`. That avoids the declaration, because foreground-service location tied to a user-initiated "Start Duty" is permitted.
- Consider distributing the app **outside Play** (direct APK or MDM) for company-owned phones. Play policy then does not apply, but Play Integrity attestation and auto-updates are lost. This is a business trade-off.
- Build an in-app "Phone health check" screen: battery-optimisation status (`isIgnoringBatteryOptimizations`), location permission level, GPS enabled, auto-time enabled, and deep links to OEM autostart pages per manufacturer, following the dontkillmyapp.com instructions. Use server-side heartbeat gaps (no points for more than 15 min while on duty) to flag a likely OEM kill and push a notification or SMS.
- FLP vs raw GPS: FLP is better for battery and for warm-up in cities. The raw `LocationManager` GPS_PROVIDER is useful as (a) a fallback on phones without Google Play services (some Huawei, some cheap phones) and (b) an anti-fraud cross-check, because GNSS fixes carry satellite-derived time.

### Gaps
- Could not fetch the current Play Console background-location declaration form or the Play "foreground service type declaration" requirement (introduced for API 34 targeting). Video and text requirements above come from 2020–2021 guides.
- dontkillmyapp.com was not reachable. Its current 2026 rankings and HyperOS/ColorOS 15 specifics are unverified.
- Android 16 "all apps" behaviour changes (JobScheduler quota changes, any location changes) were not covered by the fetched page.
- No authoritative source found on whether Google accepts "fleet/employee tracking" as an acceptable use case for `REQUEST_IGNORE_BATTERY_OPTIMIZATIONS`. Safer to send the user to settings via `ACTION_IGNORE_BATTERY_OPTIMIZATION_SETTINGS`.

---

## Q2. B4A specifics: libraries, services vs receivers, targetSdk/minSdk, camera, image compression, SQLite, HTTP, biometrics, KeyStore

### Takeaway
Erel's B4X "Background location tracking" tutorial is the base pattern. It uses a foreground service with a notification, started from the Activity, plus a boot receiver when `ACCESS_BACKGROUND_LOCATION` is granted. Use B4A 13.7+ for targetSdkVersion 36 and edge-to-edge. Use FusedLocationProviderGMS (FusedLocationProviderClient) or the GPS library. Capture photos with CamEx2/Camera2 inside the app, resize and recompress in B4A, and upload with `HttpJob.PostMultipart`. BiometricManager works for the biometric prompt, but key-bound KeyStore encryption needs inline Java.

### Cited Findings

**Background location pattern (Erel's tutorial)**
- The tutorial is a foreground service that keeps the process alive, with the current location in the notification. It was updated for targetSdkVersion 34, which required `FOREGROUND_SERVICE_LOCATION`. Starting after boot needs `ACCESS_BACKGROUND_LOCATION`, which the user grants in Settings; the app can only open Settings. The boot receiver checks the permission and calls `StartForegroundService`. — [B4X: Background location tracking (thread 99873)](https://www.b4x.com/android/forum/threads/background-location-tracking.99873/) (via search summary)
- `StartForegroundService` "doesn't actually move the service to the foreground state". The service must call `Service.StartForeground` in `Service_Start`, or the tracker is destroyed. — [B4X: Receivers and Services in 2023+](https://b4x.com/android/forum/threads/receivers-and-services-in-2023.145370/page-2); [B4X: Really confused about OnBoot Receiver](https://www.b4x.com/android/forum/threads/really-confused-about-onboot-receiver.146498/) (via search summary)
- Erel: on Android 12+, starting a service from the background fails; starting it from the foreground works. Use `StartReceiverAt` for scheduled starts. For FGS crash reports on Android 14 he advises switching non-FGS background work to **receivers**. — [B4X: Crash reports – ForegroundServiceStartNotAllowedException](https://www.b4x.com/android/forum/threads/crash-reports-foregroundservicestartnotallowedexception-and-classcastexception.146189/); [B4X: Receivers and Services in 2023+](https://b4x.com/android/forum/threads/receivers-and-services-in-2023.145370/page-2) (via search summary)
- A targetSdk 34 background-location app crash was fixed by adding `android.permission.FOREGROUND_SERVICE_LOCATION`. — [B4X: Problem when setting targetSdkVersion=34 on background location app](https://www.b4x.com/android/forum/threads/problem-when-setting-targetsdkversion-34-on-background-location-app.162040/) (via search summary)
- If automatic foreground mode is set to "never", you must call `Service.StartForeground` yourself or the app crashes. — [B4X: Automatic Foreground Mode](https://www.b4x.com/android/forum/threads/automatic-foreground-mode.90546/) (via search summary)

**Location libraries**
- The original **FusedLocationProvider** library is tied to Play Services versions: v1.30+ for Play Services 27+, v1.10 for older. That post is about 4 years old. — [B4X: FusedLocationProvider](https://www.b4x.com/android/forum/threads/fusedlocationprovider.50614/) (via search summary)
- **FusedLocationProviderGMS** (by Ivica Golubovic) uses `FusedLocationProviderClient` instead of the deprecated API. — [B4X: FusedLocationProviderGMS (Latest)](https://www.b4x.com/android/forum/threads/fusedlocationprovidergms-latest.147968/) (via search summary). There is a forum thread on mock locations with this library: [B4X: Mock Locations with New Fused_Location_Provider_GMS lib](https://www.b4x.com/android/forum/threads/mock-locations-with-new-fused_location_provider_gms-lib.159996/). There is also a cross-platform [B4X Fused Location b4xlib](https://www.b4x.com/android/forum/threads/b4x-fused-location-b4xlibrary.116055/).

**targetSdk / B4A versions**
- B4A 13.40 (beta, 2025) shipped a new SDK based on Android SDK 36. Erel: moving from 34 to 35 is "simple" and "mandatory" for Play, with edge-to-edge enabled at 35. — [B4X: B4A v13.40 BETA – with a shiny new SDK](https://www.b4x.com/android/forum/threads/b4a-v13-40-beta-with-a-shiny-new-sdk.167679/) (via search summary)
- **B4A 13.7** adds targetSdkVersion = 36 support and edge-to-edge. A new `#EdgeToEdgeOldDevices` attribute applies edge-to-edge on older devices for consistency. — [B4X: B4A v13.7 targetSdkVersion = 36 + Edge to Edge](https://www.b4x.com/android/forum/threads/b4a-v13-7-targetsdkversion-36-edge-to-edge.171756/); [beta thread](https://www.b4x.com/android/forum/threads/b4a-v13-7-beta-targetsdkversion-36-edge-to-edge.171685/) (via search summary)
- Forum reports of keyboard/IME layout breakage at targetSdk 35/36 (adjustResize with edge-to-edge). — [B4X: IME broken at targetSDKVersion 35 and 36](https://www.b4x.com/android/forum/threads/ime-broken-at-targetsdkversion-35-and-36.168179/); [B4X: B4A v13.7 IME unsupported with SDK 36](https://www.b4x.com/android/forum/threads/b4a-v13-7-ime-unsupported-with-sdk-36-is-it-ime-bug.171772/) (via search summary)
- Some third-party UI libraries broke with B4A 13.40 and AppCompat (example: AX_CustomAlertViewDialog). — [B4X thread](https://www.b4x.com/android/forum/threads/ax_customalertviewdialog-incompatibility-with-b4a-v13-40-sdk-35-36-and-appcompatactivity.167871/) (via search summary)
- Forum thread on the Play warning "App must target Android 16 (API level 36) or higher". — [B4X thread 171610](https://www.b4x.com/android/forum/threads/app-must-target-android-16-api-level-36-or-higher.171610/)

**Camera and photos**
- CameraEx (old Camera API) works on Android 2.3+. **Camera2 + CamEx2 class** works on Android 5+. The Camera2 library "should be used together with CamEx2 class". — [B4A Camera2 docs](https://www.b4x.com/android/help/camera2.html) (via search summary)
- CamEx2 capture size: set `PreviewSize`/capture size before `PrepareSurface` to control resolution. — [B4X: camera2 CamEx2 set CaptureSize](https://www.b4x.com/android/forum/threads/camera2-camex2-set-capturesize.163560/) (via search summary)
- Intent-based camera (system camera app): save to a temp file via FileProvider, then upload. The returned intent may hold only a thumbnail. — [B4X: Intent Based Camera (Erel)](https://www.b4x.com/android/forum/threads/intent-based-camera.69215/) (via search summary)
- Reducing capture size to about 800×600 and setting JPEG quality reduced storage. Bitmaps can be re-encoded to JPEG at a chosen quality via `Bitmap.WriteToStream`, and `LoadBitmapResize` creates downscaled copies. — [B4X: Camera picture size](https://www.b4x.com/android/forum/threads/camera-picture-size.122188/); [B4X: Take photo and upload](https://www.b4x.com/android/forum/threads/take-photo-and-upload-to-my-online-database.97821/) (via search summary)
- Upload: Erel recommends `HttpJob.PostMultipart` over hand-built multipart. — [B4X: Upload file](https://www.b4x.com/android/forum/threads/upload-file.84384/) (via search summary)

**Biometrics and KeyStore**
- **BiometricManager** library (B4X) wraps AndroidX `BiometricPrompt` and replaces the old FingerprintManager. It requires the `androidx.biometric:biometric` artifact (colon, not slash). Flow: `CanAuthenticate`, then `Show`, then the `Complete` event. — [B4X: BiometricManager – Biometric Authentication](https://www.b4x.com/android/forum/threads/biometricmanager-biometric-authentication.111256/); [Maven artifact not found](https://www.b4x.com/android/forum/threads/maven-artifact-not-found-androidx-biometric-biometric.135289/) (via search summary)
- Pitfalls: `NONE_ENROLLED` when only face unlock is enrolled. The event may not fire when device-credential fallback is enabled. — [B4X: Problem with NONE_ENROLLED](https://www.b4x.com/android/forum/threads/solved-biometricmanager-biometric-authentication-problem-with-none_enrolled.158487/) (via search summary)
- Erel: biometric auth "doesn't give access to the key". Android can create keys that only work after authentication, but as far as he knew this had not been exposed in B4A. — [B4X: Encrypt/Decrypt string with fingerprint authentication](https://www.b4x.com/android/forum/threads/encrypt-decrypt-string-with-fingerprint-authentication.135601/) (via search summary)

### Inferences
- **Version choices.** Build with B4A 13.7+ at targetSdkVersion 36 (required for Play updates after 31 Aug 2026). For minSdkVersion, 21 (Android 5, Camera2 and modern TLS) is the practical floor for "old low-end phones". 23 (Android 6, runtime permissions and Doze) or 24 is cleaner and drops very few devices in 2026. Check the minSdk of each AndroidX library you add (biometric, camera) before choosing. Test edge-to-edge and keyboard behaviour on API 35/36 devices early; forum IME issues show this is a real pitfall.
- **Module layout in B4A.**
  - `Tracker` service: FGS, location type, partial wakelock only while driving.
  - `Starter` / `B4XPages` UI.
  - `BootReceiver` receiver: starts the Tracker only if background location is granted; otherwise posts a "resume" notification.
  - `SyncReceiver`, or a timer inside Tracker, for uploads.
  - SQLite (`SQL` library) for the outbox, using WAL mode and transactions for batch inserts.
- **Photos.** Prefer **CamEx2 (in-app camera)** over the camera intent for evidence photos. The intent hands control to any camera app (some allow picking from the gallery or editing), and you lose control of timestamps and overlays. After capture: `LoadBitmapResize` to a long edge of 1280 px (receipts) or 1024 px (odometer / pump meter), burn in the overlay text with a Canvas, write JPEG at quality 60–75, and target 120–300 KB. Store the original-capture SHA-256 and metadata in SQLite.
- **HTTP.** Use OkHttpUtils2 `HttpJob` (built on OkHttp). Set a sensible timeout (`job.GetRequest.Timeout`), send `Authorization: Bearer` and `Idempotency-Key` headers, and gzip GPS batches. Run one upload job at a time from the outbox loop.
- **PIN + biometric.** Store a server-issued Sanctum token encrypted locally. The PIN unlocks the app offline: keep a salted PBKDF2/Argon-like hash of the PIN locally and rate-limit attempts. Biometric is a convenience unlock after the first PIN login. True KeyStore-bound encryption (`setUserAuthenticationRequired`) needs inline Java or a custom library. The simpler and acceptable approach is: AES key stored in Android KeyStore (non-auth-bound) encrypts the token, and the app gates UI access on PIN or BiometricPrompt success.
- **Two crew members, one phone?** If a co-driver or helper shares a phone, the PIN identifies who is creating each entry. Record `user_id` per event, not per device.

### Gaps
- Could not read the B4X thread bodies directly (host blocked). The exact current library versions and manifest-editor snippets (e.g., `AddPermission(android.permission.FOREGROUND_SERVICE_LOCATION)`, `SetServiceAttribute(Tracker, android:foregroundServiceType, "location")`) should be copied from Erel's thread 99873.
- No source found on the B4A minimum supported minSdkVersion in 13.x, or on any B4A wrapper for Android KeyStore / EncryptedSharedPreferences. EncryptedSharedPreferences is itself deprecated in AndroidX Security; unverified this session.
- No B4X source found for an EXIF read/write library recommendation, or for a B4A perceptual-hash implementation.

---

## Q3. B4i / iOS background location: limits, permissions, App Store review, differences from Android

### Takeaway
iOS allows continuous background tracking if the app enables the "Location updates" background mode and starts updates while in the foreground. The status indicator / `CLBackgroundActivitySession` keeps the app alive. If iOS or the user kills the app, only significant-change, region monitoring or visits (with Always authorization) relaunch it. Apple review accepts location as a background mode if it is relevant and disclosed; employee tracking must be explained. B4i reaches the needed `CLLocationManager` calls through NativeObject or inline Objective-C.

### Cited Findings
- Apple: on some devices the OS suspends background apps; "the system enqueues location updates and delivers them when the app runs again … If your app needs updates in a more timely manner, you can ask the system to not suspend your app while location services are active." — [Apple: Handling location updates in the background](https://developer.apple.com/documentation/corelocation/handling-location-updates-in-the-background)
- Enable the "Location updates" background mode capability (adds Info.plist keys). Create a `CLBackgroundActivitySession` before going to the background. Create a `CLServiceSession` requiring `whenInUse` or `always` in the foreground. After termination, "you must recreate the CLServiceSession immediately upon launch in the background." "For Always authorization, inform the user that location updates arrive in the background." — [same Apple page](https://developer.apple.com/documentation/corelocation/handling-location-updates-in-the-background)
- If the app terminates, "it should restart those APIs upon launch … the system resumes the delivery of queued location updates." — [same Apple page](https://developer.apple.com/documentation/corelocation/handling-location-updates-in-the-background)
- App Review Guideline **2.5.4**: "Multitasking apps may only use background services for their intended purposes: VoIP, audio playback, location, task completion, local notifications, etc." Guideline **5.1.5**: use Location Services "only when it is directly relevant to the features and services"; "notify and obtain consent before collecting, transmitting, or using location data"; explain the purpose. Guideline **2.4.2**: apps "should not rapidly drain battery". — [Apple App Store Review Guidelines](https://developer.apple.com/app-store/review/guidelines/)
- Third-party guidance: without `location` in `UIBackgroundModes` and `allowsBackgroundLocationUpdates`, updates stop in the background. When-In-Use apps can keep running in the background while location is active. Only region monitoring, significant-change and visits can relaunch a terminated app, and only with Always. — [Capgo blog](https://capgo.app/blog/why-ios-stops-background-location-updates-capacitor/) (secondary; another blog claims Always is needed for any background updates, so the sources conflict)
- **B4i**: Erel's tutorial enables the background location mode and calls `startUpdatingLocation` through NativeObject on the `LocationManager`. "Your app will continue to run in the background and receive location updates until you call LocationManager.Stop or until the user kills the app." Significant-change service: notifications when the device moves "500 meters or more", "not … more frequently than once every five minutes". It can relaunch a terminated app into the background. A pitfall with an inline `isSignificantLocationAvailable` call is noted. **iLocationPlus** (by Marcel) supports the significant-change service. Posts date from about 2016–2019. — [B4X: B4i Background location tracking (thread 50246)](https://www.b4x.com/android/forum/threads/background-location-tracking.50246/); [B4X: iOS Location Tracking in Background](https://www.b4x.com/android/forum/threads/ios-location-tracking-in-background.97016/); [B4X: Geofence – Monitoring a region](https://www.b4x.com/android/forum/threads/geofence-monitoring-a-region.81464/) (via search summary)

### Inferences
- **Android vs iOS for this app.** iOS has no OEM killers and no Play-style declaration, but the user can swipe the app away (ending continuous updates) and iOS shows a location indicator. For drivers, use: continuous updates during duty (`desiredAccuracy` about 10–100 m, `distanceFilter` 50–100 m, `pausesLocationUpdatesAutomatically = false` or `activityType = .automotiveNavigation`/`.otherNavigation`), plus significant-change monitoring as a safety net to relaunch and resume. Request Always only after When-In-Use, with clear wording.
- The newer Apple APIs (`CLBackgroundActivitySession`, `CLServiceSession`, `CLLocationUpdate.liveUpdates`) are Swift-centric. B4i supports inline Objective-C, so expect to use classic `CLLocationManager` via NativeObject, or write a small Objective-C wrapper. Test on iOS 17/18/26.
- The iOS offline queue and photo pipeline can share the same B4X code (B4XPages, SQL, HttpJob, B4XBitmap) using the cross-platform B4X libraries. Mock-location detection is much weaker on iOS. iOS 15+ has `CLLocationSourceInformation.isSimulatedBySoftware` (prior knowledge, unverified this session).

### Gaps
- Could not verify how App Review treats employee/fleet tracking apps in 2026. No dedicated guideline text was found in the portion read (the page was truncated after section 5.3).
- No B4i-specific source for `CLBackgroundActivitySession` or `isSimulatedBySoftware`.
- Whether When-In-Use plus background mode is enough for continuous tracking on current iOS: the sources conflict, and Apple's page implies yes when `CLBackgroundActivitySession` is used. Verify on device.

---

## Q4. Offline-first sync design: outbox, UUID idempotency, device vs server time, conflicts, retry, photo upload, upload size caps

### Takeaway
Treat the phone as the system of record until the server acknowledges each record. Every event gets a client UUID (the idempotency key) and is written to a SQLite outbox together with wall-clock time, monotonic elapsed-realtime, GNSS fix time and boot count. A single serial uploader pushes JSON first and photos separately, with exponential backoff. The server upserts by UUID and returns the stored result for duplicates. Photos of about 1024–1280 px at JPEG quality 60–75 fit a 250–500 KB cap with room to spare.

### Cited Findings
- **Idempotency-key pattern:** the client generates a unique key per logical operation and sends it in an `Idempotency-Key` header. The server records the key (in-progress), runs the transaction, stores status code and body, marks the key completed, and replays the stored response on retries. A concurrent retry arriving mid-flight should get **409 Conflict**. Store the key and the business result in the same transaction. Pair the key with a request fingerprint to detect a reused key with different content. Stripe reportedly retains keys for 24 h. An IETF draft standardises the `Idempotency-Key` header. — [http.dev: Idempotency-Key](https://http.dev/idempotency-key); [Arpit Bhayani: Stripe's approach](https://arpitbhayani.me/videos/designing-idempotent-api-endpoints-stripe); [DEV: idempotency keys](https://dev.to/leonardkachi/idempotency-keys-your-apis-safety-net-against-chaos-j1b) (secondary sources; Stripe docs not fetched)
- Android exposes `ACTION_TIME_CHANGED` and `ACTION_TIMEZONE_CHANGED` broadcasts. Receiving them is even an exemption for starting an FGS, so the app can log clock changes. — [Android Developers: FGS background-start restrictions](https://developer.android.com/develop/background-work/services/fgs/restrictions-bg-start)
- Doze suspends network access and defers jobs. Idle apps may get network "about once a day" in App Standby. Charging releases them. — [Android Developers: Doze and App Standby](https://developer.android.com/training/monitoring-device-state/doze-standby)
- B4A pipeline elements (capture, downscale with `LoadBitmapResize`, re-encode JPEG at chosen quality, `HttpJob.PostMultipart`) are confirmed in the forum threads cited in Q2. — [B4X: Upload file](https://www.b4x.com/android/forum/threads/upload-file.84384/); [B4X: Camera picture size](https://www.b4x.com/android/forum/threads/camera-picture-size.122188/)

### Inferences
These are recommended designs, not cited facts.

- **Local schema (SQLite).**
  - `events` table:
    - `uuid`: UUIDv4, or UUIDv7 for time-ordering.
    - `type`, `payload_json`, `user_id`, `vehicle_id`, `trip_id`.
    - `device_time_utc`: `DateTime.Now`.
    - `elapsed_realtime_ms`: `SystemClock.elapsedRealtime`.
    - `boot_id`: a random ID generated on each boot and stored; or `Settings.Global.BOOT_COUNT` (API 24+).
    - `gnss_time_utc`: `Location.getTime` from the last GPS-provider fix.
    - `gnss_fix_elapsed_ms`: `Location.getElapsedRealtimeNanos`/1e6.
    - `lat`, `lng`, `accuracy`, `is_mock`, `auto_time_enabled`.
    - `sync_state`: pending / sent / acked / failed, plus `attempts`, `last_error`, `server_id`, `server_received_at`.
  - `photos`: `uuid`, `event_uuid`, `slot` (odometer / pump / receipt), `file_path`, `sha256`, `phash`, `bytes`, `width`, `height`, `sync_state`.
  - `gps_points`: append-only; batch-upload by `id` range.
- **Ordering and dependencies.** Upload the event JSON first (server creates the row with `photos_expected=3`), then each photo as its own request referencing `event_uuid` and `slot`. The server marks the event "complete" when all photos arrive. A photo upload is idempotent on (`event_uuid`, `slot`, `sha256`). This keeps a lost photo from blocking the financial record.
- **Retry/backoff.** Use exponential backoff with jitter: 30 s, 1 m, 2 m, 5 m, 15 m, capped at 30 m. Reset on connectivity-change or app-foreground. Treat 4xx validation errors (except 408/409/429) as permanent: park the item as "needs attention" in the UI and keep sending the rest. Treat 5xx and timeouts as transient. Never delete local data until the server acknowledges it. Keep photos locally for N days after ack for audit.
- **Server time vs device time.**
  - Each upload carries `device_now_utc` and `elapsed_realtime_now`. The server computes `offset = server_now - device_now`.
  - The **event time** reconstruction is `server_received_at - (elapsed_now - elapsed_at_event)`. It is valid only if `boot_id` has not changed since the event; elapsed realtime resets on reboot.
  - Compare that with `device_time_utc` and with `gnss_time_utc`. A discrepancy above a threshold (e.g., 5 min) flags "possible clock tampering".
  - Store all three times. Display the "trusted event time" with a confidence level.
- **Conflict resolution.** Most records are append-only facts (GPS points, expenses) and need no merge. For mutable records (trip status, dual-approval), use server-authoritative state machines. The client sends **commands** (e.g., `approve_payment {payment_uuid, approver_id, decision, client_time}`), not full-record overwrites. The server rejects transitions that are invalid from the current state and returns the current state. Use a `version` column (optimistic locking) for admin-edited master data such as vehicles and routes. Clients pull deltas with an `updated_since` cursor.
- **Dual-approval offline.** The second driver may approve on their own phone, possibly also offline. The approval is a separate event with its own UUID. The server accepts the first valid approval and treats later duplicates as no-ops. If the approver is on the same phone, require their own PIN, never the first driver's session.
- **Photo sizing for a 250–500 KB cap** (engineering judgement; no authoritative source found):
  - Receipts: long edge 1280–1600 px, JPEG quality about 70, often 150–350 KB. Thermal-print receipts benefit from grayscale conversion, which also saves size.
  - Odometer / pump meter digits: 1024–1280 px at quality 65–75, about 100–250 KB.
  - Enforce on the device: if output exceeds 400 KB, step quality down by 5 to a floor of 50, then reduce dimensions by 15%.
  - Keep EXIF orientation applied (rotate pixels), because many low-end phones store rotated JPEGs with an EXIF orientation flag.
- **Chunked/resumable uploads** are unnecessary at 150–350 KB per photo. A single multipart POST per photo, made idempotent, is simpler. Use tus or chunking only if a full-resolution "original" is ever uploaded.

### Gaps
- No authoritative study found (search budget exhausted) on minimum resolution for legible receipts and odometers or for OCR (e.g., ML Kit's guidance on character pixel height). The sizes above are recommendations to validate with a field pilot.
- `SystemClock.currentGnssTimeClock()` (API 29+) and `Location.getElapsedRealtimeNanos()` semantics could not be quoted. The developer.android.com reference pages returned only navigation in this tool. Verify at https://developer.android.com/reference/android/os/SystemClock and https://developer.android.com/reference/android/location/Location.

---

## Q5. Anti-fraud: mock GPS, root/integrity, in-app camera only, watermarks, EXIF, reused photos, fuel location vs track

### Takeaway
No single check is reliable. Combine: (a) the per-fix mock flag (`isMock()` / `isFromMockProvider()`, which can be spoofed); (b) Play Integrity device and app verdicts when online; (c) plausibility checks on the server (teleport speed, fuel-pump location vs track, time drift); (d) in-app-only capture with a burned-in watermark, plus server-side SHA-256 and perceptual-hash duplicate detection. Score risk and route suspicious entries to a manager rather than auto-blocking drivers who have no network.

### Cited Findings
- `isFromMockProvider()` "turns out to be unreliable (meaning sometimes fake locations are not correctly labeled as such)". — [LocationAssistantV2 README (GitHub)](https://github.com/soniiic/LocationAssistantV2/blob/master/README.md)
- The `ALLOW_MOCK_LOCATION` setting was deprecated in API 23 and is "no longer used", so checks based on it always return false on API 23+. — [MockLocationDetector (GitHub)](https://github.com/smarques84/MockLocationDetector); [B4X: ALLOW_MOCK_LOCATION thread](https://www.b4x.com/android/forum/threads/allow_mock_location.76602/latest) (via search summary)
- Google Play services `FusedLocationProviderApi.KEY_MOCK_LOCATION` "is deprecated. Prefer to use LocationCompat.isMock() from the compat libraries." — [Google Play services reference: FusedLocationProviderApi](https://developers.google.com/android/reference/com/google/android/gms/location/FusedLocationProviderApi) (via search summary)
- B4X forum thread on mock locations with the FusedLocationProviderGMS library. — [B4X](https://www.b4x.com/android/forum/threads/mock-locations-with-new-fused_location_provider_gms-lib.159996/)
- **Play Integrity** (changes from May 2025): on Android 13+, `MEETS_DEVICE_INTEGRITY` requires a hardware-backed positive verified-boot signal. `MEETS_STRONG_INTEGRITY` requires a security update from the last 12 months. On Android 13+, the app must have been installed or updated by Google Play for an app-recognised verdict. — [Android Developers: Improved verdicts in Android 13+](https://developer.android.com/google/play/integrity/improvements); [Android Enterprise community: Play Integrity behavioral changes](https://www.androidenterprise.community/kb/announcements/google-play-integrity-api-behavioral-changes/11228)
- Apps can receive `ACTION_TIME_CHANGED`, so clock changes can be detected and logged on device. — [Android Developers](https://developer.android.com/develop/background-work/services/fgs/restrictions-bg-start)

### Inferences
- **Mock GPS.**
  - Check the per-fix flag: `isMock()` on API 31+, `isFromMockProvider()` on API 18–30. In B4A, access it through JavaObject on the Location object, or use the FusedLocationProviderGMS fields.
  - Check installed apps that hold the `ACCESS_MOCK_LOCATION` permission (a heuristic; package visibility on Android 11+ needs `<queries>` or `QUERY_ALL_PACKAGES`, which has its own Play policy).
  - Check whether developer options are enabled (`Settings.Global.DEVELOPMENT_SETTINGS_ENABLED`).
  - Server-side: implied speed between consecutive points above 150 km/h for a truck, accuracy exactly 0 or constant, altitude/bearing/speed always 0, no GNSS satellite count (GnssStatus), fixes arriving while the phone reports airplane mode.
- **Rooting.** Play Integrity (requires Play-installed builds and network) is the strongest signal. Offline, use heuristics (su binary, test-keys, Magisk package names). These are easily hidden, so treat them as risk score inputs only.
- **Clock tampering.** Log `Settings.Global.AUTO_TIME` (automatic date and time) with each event. Use the elapsed-realtime reconstruction and GNSS fix time (Q4). Log `ACTION_TIME_CHANGED` broadcasts with before and after values. Server flags: an event time later than server receipt, an event time before the previous event's time, or drift above a threshold.
- **Photos.**
  - Disable gallery pick for evidence slots. Capture with CamEx2 inside the app.
  - Burn a watermark into the pixels: date and time (trusted time plus device time), lat/lng, vehicle number, driver ID and event UUID short code.
  - Write EXIF too, but EXIF is trivially editable, so the burned overlay and server metadata are what count.
  - Compute SHA-256 at capture and send it with the event JSON, before the photo upload. The server rejects a photo whose hash does not match the declared value.
  - The server computes a perceptual hash (pHash/dHash, 64-bit) and flags Hamming distance ≤ 6–10 against the same driver's or vehicle's last N photos. This catches the same receipt photographed twice and screen-recaptures.
  - Optional: OCR the litres and amount on the receipt and compare with the entered values.
- **Fuel/toll vs track.** The server checks that the fuel event's GPS is within X m (e.g., 300 m) of the nearest track point at that time, that the vehicle was stationary for at least 3–5 minutes around the event, and, if pump POIs are known, that the location is within N m of a fuel station. Compare litres with the tank-capacity and odometer delta (km/l plausibility). Do the same for tolls against known toll plaza coordinates (FASTag statements in India could be reconciled later; out of scope).
- **Policy design.** Do not block saving offline. Record everything with flags, and surface flags to managers in the dual-approval and payment-release flow. Drivers in no-network zones must never be locked out.

### Gaps
- No primary-source quote obtained for `Location.isMock()` (API 31) docs. Its existence and API level are from prior knowledge plus the Play services deprecation note pointing to `LocationCompat.isMock()`.
- No source gathered (search budget exhausted) for a recommended pHash library for PHP/Laravel (e.g., `jenssegers/imagehash`, maintenance status unknown) or for perceptual-hash thresholds.
- No source on Play policy for `QUERY_ALL_PACKAGES` in this use case.

---

## Q6. Driving-time logging ideas from ELD / hours-of-service apps

### Takeaway
Borrow the ELD state model: Off Duty, Sleeper, Driving, On-Duty-Not-Driving. Record explicit driver-swap events. Switch automatically to "Driving" when speed exceeds a threshold, and prompt the driver when stationary. Keep an append-only, edit-with-annotation audit log. This research session could not verify the US FMCSA rule text, because the hosts were blocked and the search budget was exhausted.

### Cited Findings
- No source could be fetched for this question (fmcsa.dot.gov and ecfr.gov blocked; search budget exhausted). See Gaps.

### Inferences
- **Unverified prior knowledge** of the US ELD technical specification (49 CFR Part 395, Subpart B, Appendix A): the ELD automatically records "Driving" once the vehicle reaches about 5 mph. When the vehicle has stopped for 5 consecutive minutes, it prompts the driver, and if there is no response within 1 minute it switches to On-Duty-Not-Driving. It records location at least every 60 minutes while driving, at reduced precision. Edits require an annotation and the original record is preserved. Team drivers each have their own log, and the co-driver can switch who is driving. Verify at https://www.ecfr.gov/current/title-49/subtitle-B/chapter-III/subchapter-B/part-395 before relying on it.
- **Suggested app design** (recommendation, not a regulatory requirement in India or UAE):
  - Duty states per crew member: OFF, ON_DUTY (not driving), DRIVING, REST/SLEEPER. Only one crew member per vehicle can be DRIVING at a time.
  - **Driver change event:** outgoing driver taps "Hand over", incoming driver enters their PIN. The event records odometer (with photo), GPS and time. The server checks that the two crew members' phones were within about 200 m of each other at that time.
  - **Auto-detection:** speed above 10–15 km/h sustained for 60 s (using GPS speed) while the user is ON_DUTY means a candidate DRIVING segment. Stationary for 5 min means prompt "Still driving?", and with no answer for 1 min, switch to ON_DUTY. The phone that is moving with the truck but marked as helper is not set to driving. Activity Recognition API "IN_VEHICLE" transitions can also be used; they are an FGS-start exemption on Android 12+ per Q1.
  - Compute continuous-driving and daily-driving totals on the server from the event log. Show warnings in the app, such as a break reminder after X hours.
  - Keep the log append-only. Corrections are new "edit" events with a reason and approver, mirroring ELD annotation rules.

### Gaps
- Exact FMCSA thresholds and wording are unverified in this session.
- No research done on Indian (Motor Transport Workers Act / MV Act driving-hour limits) or UAE (RTA heavy-vehicle driver rest rules). Those are legal topics, probably covered by another researcher.

---

## Q7. Laravel side: Sanctum mobile auth, idempotent endpoints, high-volume GPS ingest, partitioning, retention, image storage

### Takeaway
Use Sanctum personal access tokens per device: `createToken($request->device_name)`, scoped abilities, and expiry. Implement idempotency by a unique `client_uuid` column plus upsert or `firstOrCreate` inside a transaction. Ingest GPS as batched multi-row inserts, queued if needed, into a table range-partitioned by month (composite PK including the timestamp). Drop or archive old partitions instead of `DELETE`. Keep photos in object storage with only metadata in MySQL.

### Cited Findings
- Sanctum supports SPAs, mobile apps and simple token APIs. For mobile, the login route validates `email`, `password` and **`device_name`**, then returns `$user->createToken($request->device_name)->plainTextToken`. Tokens can carry abilities. Global expiry is set in `config/sanctum.php` `expiration` (minutes; default `null` = never). A per-token expiry can be passed to `createToken()`. `php artisan sanctum:prune-expired --hours=24` removes old tokens and can be scheduled daily. — [Context7: laravel/sanctum](https://context7.com/laravel/sanctum); [oneuptime: Laravel Sanctum authentication, Jan 2026](https://oneuptime.com/blog/post/2026-01-26-laravel-sanctum-authentication/view); [Stanza: Sanctum introduction](https://www.stanza.dev/courses/laravel-api-development/api-authentication/laravel-api-development-sanctum-introduction); [Stanza: token abilities](https://www.stanza.dev/courses/laravel-authentication/api-token-auth/laravel-authentication-token-abilities) (secondary; official https://laravel.com/docs/12.x/sanctum not reachable)
- **MySQL partitioning for time-series:**
  - `DROP PARTITION` is a metadata operation (milliseconds), versus a row-by-row `DELETE` that writes undo and binlog for every row.
  - Every unique key, including the primary key, must include the partition column, e.g. PK (`id`, `recorded_at`).
  - **Foreign keys are not supported on partitioned tables.**
  - Partition pruning needs raw range predicates on the column, not `YEAR()`/`MONTH()` wrappers.
  - Pre-create next month's partition by reorganising the `MAXVALUE` catch-all.
  - Archive via `EXCHANGE PARTITION` and then drop.
  - Automate with a scheduled procedure or event.
  — [oneuptime: MySQL partitioning for data archival, Mar 2026](https://oneuptime.com/blog/post/2026-03-31-mysql-use-partitioning-for-data-archival/view); [oneuptime: DROP PARTITION](https://oneuptime.com/blog/post/2026-03-31-mysql-drop-partition/view); [oneuptime: Partition by year and month](https://oneuptime.com/blog/post/2026-03-31-mysql-partition-by-year-and-month/view); [MariaDB: automatic partition maintenance](https://mariadb.com/resources/blog/automatic-partition-maintenance-mariadb); [Keboola: MySQL partitioning](https://500.keboola.com/improving-mysql-performance-with-table-partitioning/) (vendor and tutorial sources; confirm in the MySQL reference manual, "Partitioning limitations")
- Idempotent endpoint pattern (key + stored response + 409 on concurrent duplicate): see Q4 sources. — [http.dev: Idempotency-Key](https://http.dev/idempotency-key)

### Inferences
- **Scale reality check.** 50 crew phones × 1 point every 30–60 s × 12 h/day is about 36k–72k points per day, or 1–2 million per month. "10 lakh+" rows is reached within a month, which is still small for MySQL if indexed and partitioned. 500 phones is about 20M per month, where partitioning and retention matter.
- **GPS table design.**
  - `gps_points`: `id BIGINT UNSIGNED AUTO_INCREMENT`, `user_id INT`, `vehicle_id INT`, `trip_id INT NULL`, `recorded_at DATETIME(3)` (device-reconstructed UTC), `received_at`, `lat DECIMAL(9,6)` or `INT` microdegrees, `lng`, `speed_kmh SMALLINT`, `bearing SMALLINT`, `accuracy_m SMALLINT`, `flags TINYINT` (mock, low-accuracy), `client_seq BIGINT`.
  - `PRIMARY KEY (id, recorded_at)`, `INDEX (vehicle_id, recorded_at)`, `INDEX (user_id, recorded_at)`.
  - `UNIQUE (user_id, client_seq, recorded_at)` for idempotent re-sends, then `INSERT IGNORE` or `upsert()`.
  - `PARTITION BY RANGE COLUMNS(recorded_at)` monthly.
  - No FKs on this table; enforce integrity in the application.
- **Ingest.** The endpoint accepts a gzipped JSON or CSV batch (up to about 500–1000 points). Validate lightly, then do a single multi-row insert (`DB::table('gps_points')->insertOrIgnore($rows)` in chunks of about 500). Optionally push to a queue (Redis + Horizon) and return 202 for very high volume. Update a small `vehicle_last_position` table (one row per vehicle, upsert) for the live map, so the dashboard never scans the big table.
- **Retention and downsampling.** Keep raw points 3–6 months (hot). A nightly job writes a downsampled track: one point per 1–2 min, or Douglas-Peucker simplified polylines per trip, stored in `trip_tracks` (polyline text) for long-term audit. Then `DROP` or `EXCHANGE` old partitions. Keep event-linked points (around fuel/toll events) indefinitely.
- **Events and photos.** `trip_events.client_uuid CHAR(36)` or `BINARY(16)` with a UNIQUE index. The endpoint uses `firstOrCreate(['client_uuid' => ...], [...])` inside a transaction and returns 200 with the existing record on a duplicate. Photos go to S3-compatible storage (AWS S3 Mumbai or Middle East region, Cloudflare R2, Wasabi, or local disk with backups) under keys like `events/{yyyy}/{mm}/{event_uuid}/{slot}.jpg`. The `event_photos` table holds `sha256`, `phash BIGINT UNSIGNED`, size and dimensions. Serve photos via signed temporary URLs (`Storage::temporaryUrl`). Do the pHash computation in a queued job.
- **Auth hardening.** Issue one token per device with abilities (e.g., `crew:write`). Bind a device ID and app version, and record them per request. Set expiry to about 30–90 days with silent refresh on online use. The local PIN never leaves the device. Revoke tokens from the admin panel when a phone is lost.
- **Server upload limits.** Set PHP `upload_max_filesize`/`post_max_size` and the Nginx `client_max_body_size` slightly above the client cap, e.g. 1 MB. Validate `image/jpeg` and max dimensions in Laravel.

### Gaps
- Could not fetch the official Laravel 12/13 Sanctum docs (blocked). Verify the config key names and the current version's behaviour.
- No primary benchmark for MySQL 8 insert throughput for this row shape.
- MySQL partition limitations (no FKs) should be confirmed in the official manual: https://dev.mysql.com/doc/refman/8.0/en/partitioning-limitations.html (not fetched).

---

## Q8. Maps and routing for India and UAE: Google Maps Platform 2025–26 pricing, Mappls, HERE truck routing, OSM/OSRM/GraphHopper

### Takeaway
Since March 2025, Google Maps Platform gives per-SKU free monthly caps (Essentials 10k, Pro 5k, Enterprise 1k globally) instead of a $200 credit, with higher caps for India-billed accounts (Pro 35k, Enterprise 7k). Truck routing is not offered by Google. It is available from HERE (truck attributes in Routing v8), Mappls/MapmyIndia (truck profile in the Directions API) and self-hosted GraphHopper or OSRM with custom truck profiles. OSM truck data (maxweight/maxheight) is sparse, especially in India.

### Cited Findings
- **Google Maps Platform (from 1 Mar 2025):** the $200 monthly credit was replaced by per-SKU free usage. Essentials SKUs get 10,000 free events per month, Pro 5,000, Enterprise 1,000. Map Tiles API is an exception at 100,000. Caps are per SKU and not pooled. — [Google: Pricing categories](https://developers.google.com/maps/billing-and-pricing/pricing-categories) (via search summary); [Google: Billing FAQ on 2025 changes](https://developers.google.com/maps/billing-and-pricing/faq); [mapatlas.eu 2026](https://mapatlas.eu/blog/google-maps-api-pricing-2026); [Woosmap 2026](https://www.woosmap.com/blog/google-maps-api-pricing-breakdown)
- **India pricing:** India-billed accounts get higher caps: **Pro (India) 35,000** and **Enterprise (India) 7,000** free requests per month. The Essentials-India figure was cut off in the source. — [Google: Pricing categories](https://developers.google.com/maps/billing-and-pricing/pricing-categories) (via search summary). Google also published an India-specific developer announcement. — [Google India blog: Helping developers in India build more with Google Maps Platform](https://blog.google/intl/en-in/products/explore-communicate/helping-developers-in-india-build-more-with-google-maps-platform/) (not fetched; contents unverified)
- Third-party sources describe Google subscription plans (Starter about $100/mo for 50k calls, Essentials about $275/mo for 100k, Pro about $1,200/mo for 250k) covering Essentials and Pro SKUs. Not confirmed on Google's own pages, and India applicability is unknown. — [Woosmap](https://www.woosmap.com/blog/google-maps-api-pricing-breakdown)
- **Mappls / MapmyIndia:** the Directions API supports routing profiles for "passenger vehicles, trucks, two-wheelers, and pedestrians". It is sold on AWS Marketplace as SaaS with monthly usage fees; no rates are listed. — [AWS Marketplace: MapmyIndia](https://aws.amazon.com/marketplace/pp/prodview-6if2aybofgvqk). Historic (2015) pricing: free for development, commercial plans from Rs 25,000/month for 10,000 calls. Outdated. — [MediaNama 2015](https://www.medianama.com/2015/03/223-mapmyindia-launches-its-map-api-a-decade-after-google-maps/)
- **HERE:** the current pricing page lists a **Limited** plan (1,000 free requests per day), a pay-per-transaction **Base** plan, and **Enterprise**. Third-party sources conflict on the Base free allowance (30k vs 250k transactions per month) and cite about $1 per 1,000 routing transactions beyond it. Truck attributes (height, weight, hazmat) are a feature of Routing v8; no truck-specific price was found. — [HERE: Pricing](https://developer.here.com/pricing); [HERE Base plan](https://developers.here.com/plans); [ki-syndikat.de review](https://www.ki-syndikat.de/tools/here-routing/); [GeoConnexion 2018](https://www.geoconnexion.com/news/here-announces-new-pricing-plan) (conflicting; verify)
- **GraphHopper (self-hosted, Apache 2.0):** custom models can block edges by max height, width, weight and length for a truck profile. Self-hosters found `max_weight`/`max_height` = "infinity" (restrictions not applied) unless the encoded values are configured, causing differences from the hosted service. The hosted "truck" profile has fixed dimensions (e.g., height 3.7 m). — [GraphHopper forum: Custom model for trucks](https://discuss.graphhopper.com/t/custom-model-for-trucks/7632); [GraphHopper forum: Different route using truck profile](https://discuss.graphhopper.com/t/different-route-using-truck-profile-in-web-graphhopper-and-locally/9494); [GraphHopper Directions API docs](https://docs.graphhopper.com/openapi/section/tomtom)
- **OSRM:** Lua profiles; community truck profiles exist (osrm-profiles-contrib), built on the car profile. — [Geofabrik: Routing with OSRM and GraphHopper](https://www.geofabrik.de/en/data/routing.html); [OSRM-talk 2015](https://lists.openstreetmap.org/pipermail/osrm-talk/2015-November/001049.html)
- **OSM truck data quality:** volunteers may not map bridge heights or weight limits, so commercial data may be more complete for truck routing. Coverage "depends a lot on the region". — [OSM Help: OSM for commercial truck routing](https://help.openstreetmap.org/questions/74336/osm-for-commercial-truck-routing)

### Inferences
- **For the driver app:** the app does not need turn-by-turn navigation (drivers use Google Maps or the Mappls app). The phone app needs at most a small map to confirm the event location. Use OSM raster/vector tiles (OpenFreeMap, MapTiler, or self-hosted) through B4A's OSMDroid wrapper, or Google Maps SDK for Android (`GoogleMaps` library in B4A). Under prior pricing, mobile native dynamic maps were billed at $0 with unlimited use; verify for 2026. **Avoid map loads in the background service entirely.**
- **For the web dashboard (Laravel):** use Leaflet or MapLibre with OSM tiles for live positions and track playback (no per-load cost). Use Google or Mappls only for geocoding or search, within the free caps. Mappls has better Indian address and POI coverage. Google is stronger in the UAE.
- **For truck routing / ETA / route deviation:** start with self-hosted **OSRM or GraphHopper** on the India and GCC OSM extracts. A car-like profile with a truck speed cap of 60–80 km/h is enough for ETA and planned-vs-actual distance. Use HERE Routing v8 (truck mode) or Mappls truck routing per request only where height or weight restrictions matter, such as UAE city truck bans and Indian city no-entry times. Verify India truck-restriction tagging density by counting `maxweight`/`maxheight` tags in the Geofabrik India PBF.
- **Cost guardrails.** Cache geocodes. Snap-to-road and distance calculations can run on own OSRM (`/match`) instead of Google Roads API.

### Gaps
- Google Essentials-India free cap, per-1,000 India prices, and whether Maps SDK for Android/iOS remains free and unlimited in 2026 were not confirmed (developers.google.com and mapsplatform.google.com blocked).
- Current Mappls API price list and free tier not found. Contact Mappls sales or check about.mappls.com/api.
- HERE free tier conflicts (1k/day vs 30k or 250k/month). Check the HERE platform pricing page directly.
- No source on Google Routes API truck support (believed absent) or on UAE-specific truck routing datasets.
