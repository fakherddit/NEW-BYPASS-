#import <Foundation/Foundation.h>
#import <UIKit/UIKit.h>

// TODO: Replace with your deployed Render URL
static NSString *const kAuthServerURL = @"https://new-bypass-1.onrender.com/validate"; 

@interface KeyAuth : NSObject
+ (void)validateKey:(NSString *)key completion:(void (^)(BOOL success, NSString *message, NSString *expiryDate))completion;
+ (NSString *)getSavedKey;
+ (void)saveKey:(NSString *)key;
@end

@implementation KeyAuth

+ (NSString *)getSavedKey {
    return [[NSUserDefaults standardUserDefaults] stringForKey:@"saved_license_key"];
}

+ (void)saveKey:(NSString *)key {
    [[NSUserDefaults standardUserDefaults] setObject:key forKey:@"saved_license_key"];
    [[NSUserDefaults standardUserDefaults] synchronize];
}

+ (void)validateKey:(NSString *)key completion:(void (^)(BOOL, NSString *, NSString *))completion {
    if (!key || key.length == 0) {
        completion(NO, @"Key cannot be empty", nil);
        return;
    }

    NSString *hwid = [[[UIDevice currentDevice] identifierForVendor] UUIDString];
    NSDictionary *payload = @{@"key": key, @"hwid": hwid};
    
    NSError *error;
    NSData *jsonData = [NSJSONSerialization dataWithJSONObject:payload options:0 error:&error];
    
    NSMutableURLRequest *request = [NSMutableURLRequest requestWithURL:[NSURL URLWithString:kAuthServerURL]];
    [request setHTTPMethod:@"POST"];
    [request setValue:@"application/json" forHTTPHeaderField:@"Content-Type"];
    [request setHTTPBody:jsonData];
    
    NSURLSessionDataTask *task = [[NSURLSession sharedSession] dataTaskWithRequest:request completionHandler:^(NSData *data, NSURLResponse *response, NSError *error) {
        if (error) {
            NSLog(@"[KeyAuth] Network Error: %@", error);
            dispatch_async(dispatch_get_main_queue(), ^{
                completion(NO, @"Network error. Check connection.", nil);
            });
            return;
        }
        
        NSError *jsonError;
        NSDictionary *json = [NSJSONSerialization JSONObjectWithData:data options:0 error:&jsonError];
        
        if (jsonError || !json) {
            dispatch_async(dispatch_get_main_queue(), ^{
                completion(NO, @"Invalid server response.", nil);
            });
            return;
        }
        
        BOOL isValid = [json[@"valid"] boolValue];
        NSString *message = json[@"message"];
        NSString *expiry = json[@"expiry_date"];
        
        dispatch_async(dispatch_get_main_queue(), ^{
            completion(isValid, message, expiry);
        });
    }];
    [task resume];
}

@end
