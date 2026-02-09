TARGET := iphone:clang:latest:14.0
INSTALL_TARGET_PROCESSES = STLoader

include $(THEOS)/makefiles/common.mk

APPLICATION_NAME = STLoader

STLoader_FILES = main.m STRootViewController.mm
STLoader_FRAMEWORKS = UIKit CoreGraphics
STLoader_CFLAGS = -fobjc-arc

STLoader_CODESIGN_FLAGS = -Sentitlements.plist
STLoader_RESOURCE_DIRS = Resources
include $(THEOS)/makefiles/application.mk
