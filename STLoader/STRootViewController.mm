#import "STRootViewController.h"
#include <spawn.h>

extern char **environ;

void runCmd(const char *cmd) {
    pid_t pid;
    const char *argv[] = {"sh", "-c", cmd, NULL};
    int status;
    posix_spawn(&pid, "/bin/sh", NULL, NULL, (char* const*)argv, environ);
    waitpid(pid, &status, 0);
}

@implementation STRootViewController

- (void)viewDidLoad {
    [super viewDidLoad];
    self.view.backgroundColor = [UIColor blackColor];

    // Title Label
    UILabel *titleLabel = [[UILabel alloc] initWithFrame:CGRectMake(0, 100, self.view.bounds.size.width, 50)];
    titleLabel.text = @"ST LOADER";
    titleLabel.textColor = [UIColor whiteColor];
    titleLabel.textAlignment = NSTextAlignmentCenter;
    titleLabel.font = [UIFont boldSystemFontOfSize:30];
    [self.view addSubview:titleLabel];

    // Status Label
    UILabel *statusLabel = [[UILabel alloc] initWithFrame:CGRectMake(0, 150, self.view.bounds.size.width, 30)];
    statusLabel.text = @"Ready to Inject";
    statusLabel.textColor = [UIColor grayColor];
    statusLabel.textAlignment = NSTextAlignmentCenter;
    statusLabel.tag = 100;
    [self.view addSubview:statusLabel];

    // Load Button
    UIButton *loadButton = [UIButton buttonWithType:UIButtonTypeCustom];
    loadButton.frame = CGRectMake((self.view.bounds.size.width - 200) / 2, self.view.bounds.size.height / 2 - 25, 200, 50);
    [loadButton setTitle:@"START CHEAT" forState:UIControlStateNormal];
    [loadButton setBackgroundColor:[UIColor redColor]];
    loadButton.layer.cornerRadius = 10;
    [loadButton addTarget:self action:@selector(loadCheat) forControlEvents:UIControlEventTouchUpInside];
    [self.view addSubview:loadButton];
}

- (void)updateStatus:(NSString *)status color:(UIColor *)color {
    UILabel *statusLabel = [self.view viewWithTag:100];
    statusLabel.text = status;
    statusLabel.textColor = color;
}

- (void)loadCheat {
    [self updateStatus:@"Injecting..." color:[UIColor yellowColor]];

    // Paths
    // NSString *dylibName = @"@SAVAGEXITER.dylib"; // Removed unused variable
    NSString *bundleDylibPath = [[NSBundle mainBundle] pathForResource:@"@SAVAGEXITER" ofType:@"dylib"]; 
    if (!bundleDylibPath) {
        // Fallback: try checking for the file directly in the bundle path if pathForResource fails
        NSString *bundlePath = [[NSBundle mainBundle] bundlePath];
        bundleDylibPath = [bundlePath stringByAppendingPathComponent:@"@SAVAGEXITER.dylib"];
    }
    
    // Determine Jailbreak Path (Rootful vs Rootless)
    NSString *targetPath = @"/Library/MobileSubstrate/DynamicLibraries/@SAVAGEXITER.dylib";
    NSString *targetPlistPath = @"/Library/MobileSubstrate/DynamicLibraries/@SAVAGEXITER.plist";
    
    // Simple check for rootless
    if ([[NSFileManager defaultManager] fileExistsAtPath:@"/var/jb/usr/lib/TweakInject"]) {
        targetPath = @"/var/jb/usr/lib/TweakInject/@SAVAGEXITER.dylib";
        targetPlistPath = @"/var/jb/usr/lib/TweakInject/@SAVAGEXITER.plist";
    }

    // Check if source exists
    if (![[NSFileManager defaultManager] fileExistsAtPath:bundleDylibPath]) {
        [self updateStatus:[NSString stringWithFormat:@"Error: Source dylib not found at %@", bundleDylibPath] color:[UIColor redColor]];
        return;
    }

    [self updateStatus:[NSString stringWithFormat:@"Copying to %@...", targetPath] color:[UIColor orangeColor]];

    // Attempt Copy
    NSError *error;
    if ([[NSFileManager defaultManager] fileExistsAtPath:targetPath]) {
        [[NSFileManager defaultManager] removeItemAtPath:targetPath error:nil];
    }
    
    BOOL success = [[NSFileManager defaultManager] copyItemAtPath:bundleDylibPath toPath:targetPath error:&error];

    if (!success) {
        // Try simplified 'cp' command
        NSString *cmd = [NSString stringWithFormat:@"cp \"%@\" \"%@\"", bundleDylibPath, targetPath];
        runCmd([cmd UTF8String]);
        
        NSString *cmd2 = [NSString stringWithFormat:@"echo '{ Filter = { Bundles = ( \"com.dts.freefireth\" ); }; }' > \"%@\"", targetPlistPath];
        runCmd([cmd2 UTF8String]);
        
        // Check again
        if ([[NSFileManager defaultManager] fileExistsAtPath:targetPath]) {
            success = YES;
        }
    } else {
        // Create Plist normally if native copy worked
        NSString *plistContent = @"{ Filter = { Bundles = ( \"com.dts.freefireth\" ); }; }";
        [plistContent writeToFile:targetPlistPath atomically:YES encoding:NSUTF8StringEncoding error:nil];
    }
    
    // Check if successful
    if (success) {
        [self updateStatus:@"Success! Opening Game..." color:[UIColor greenColor]];
        
        // Launch Game
        dispatch_after(dispatch_time(DISPATCH_TIME_NOW, (int64_t)(1.0 * NSEC_PER_SEC)), dispatch_get_main_queue(), ^{
            NSURL *url = [NSURL URLWithString:@"freefireth://"]; // Try URL Scheme
            if ([[UIApplication sharedApplication] canOpenURL:url]) {
                [[UIApplication sharedApplication] openURL:url options:@{} completionHandler:nil];
            } else {
                 // Try opening via bundle ID using workspace (private API or system cmd)
                 runCmd("open com.dts.freefireth");
                 runCmd("uiopen com.dts.freefireth");
            }
        });
    } else {
         NSString *errDesc = error ? [error localizedDescription] : @"Unknown Copy Error";
         [self updateStatus:[NSString stringWithFormat:@"Fail: %@", errDesc] color:[UIColor redColor]];
    }
}

@end
