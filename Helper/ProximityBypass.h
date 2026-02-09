#ifndef PROXIMITY_BYPASS_H
#define PROXIMITY_BYPASS_H

#import <Foundation/Foundation.h>

// =================================================================================
// @SAVAGEXITER PROXIMITY CRASH FIX
// Prevents IPA crashes when players get within 10m of enemies (Anti-Cheat Bypass)
// =================================================================================

// Global Bypass Flags
bool g_BypassProximity = true;
bool g_FakeDistanceReporting = true;
bool g_DisableCollisionCheck = true;
bool g_DisableKillValidation = true;

// Safe Distance Thresholds
static float MIN_SAFE_DISTANCE = 15.0f;   // Danger zone (<15m will crash)
static float MAX_SAFE_DISTANCE = 200.0f;  // Max engagement distance
static float FAKE_REPORT_DISTANCE = 25.0f; // Distance reported to anti-cheat

// Initialize the system
void EnableProximityBypass() {
    g_BypassProximity = true;
    g_FakeDistanceReporting = true;
    g_DisableCollisionCheck = true;
    g_DisableKillValidation = true;
    
    NSLog(@"[PROXIMITY-BYPASS] ✅ ENABLED");
    NSLog(@"[PROXIMITY-BYPASS]    Min Safe Distance: %.1fm", MIN_SAFE_DISTANCE);
    NSLog(@"[PROXIMITY-BYPASS]    Max Safe Distance: %.1fm", MAX_SAFE_DISTANCE);
    NSLog(@"[PROXIMITY-BYPASS]    Fake Distance Reporting: %.1fm", FAKE_REPORT_DISTANCE);
    NSLog(@"[PROXIMITY-BYPASS]    Collision Blocking: ENABLED");
    NSLog(@"[PROXIMITY-BYPASS]    Kill Validation: ENABLED");
}

// 1. SafeDistance() - Clamps distance to safe range for logic
// Use this for distance calculations to prevent anti-cheat triggers
float SafeDistance(float realDistance) {
    if (!g_BypassProximity) return realDistance;
    
    // If real distance is in the danger zone (< 15m), report a fake safe distance
    // This prevents the "if (distance <= 10.0f) crash" loop in the IPA
    if (realDistance < MIN_SAFE_DISTANCE) {
        // Optional: log suspicious proximity
        // NSLog(@"[PROXIMITY-BYPASS] ⚠️ Distance %.1fm -> Reporting %.1fm", realDistance, FAKE_REPORT_DISTANCE);
        return FAKE_REPORT_DISTANCE; 
    }
    
    return realDistance;
}

// 2. CanEngageEnemy() - Validates if it safe to engage
// Prevents AutoAim from locking onto enemies that are too close (causing crashes)
bool CanEngageEnemy(float distance) {
    if (!g_BypassProximity) return true;
    
    if (distance < MIN_SAFE_DISTANCE) {
        return false; // Too close, ignore this target
    }
    
    if (distance > MAX_SAFE_DISTANCE) {
        return false; // Too far
    }
    
    return true;
}

// 3. ValidateKillAttempt() - Validates kill before execution
// Aborts kill signals if enemy is dangerously close
bool ValidateKillAttempt(float distance, void* targetKey) {
    if (!g_DisableKillValidation) return true;
    
    if (distance < MIN_SAFE_DISTANCE) {
        NSLog(@"[PROXIMITY-BYPASS] ❌ BLOCKED KILL - Too close (%.1fm)", distance);
        return false;
    }
    
    return true;
}

// 4. ReportDistanceToAntiCheat() 
// Always returns a safe distance value
float ReportDistanceToAntiCheat(float realDistance) {
    if (!g_FakeDistanceReporting) return realDistance;
    return FAKE_REPORT_DISTANCE;
}

#endif
