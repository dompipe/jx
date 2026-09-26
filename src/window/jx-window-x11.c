#include <errno.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sys/wait.h>
#include <unistd.h>
#include <X11/Xlib.h>
#include <X11/Xutil.h>

static void die(const char *message) {
    fprintf(stderr, "jx-window-x11: %s: %s\n", message, strerror(errno));
    exit(1);
}

static char *read_all(FILE *fp) {
    size_t cap = 4096;
    size_t len = 0;
    char *buf = (char *)malloc(cap);
    if (!buf) {
        die("out of memory");
    }

    int ch;
    while ((ch = fgetc(fp)) != EOF) {
        if (len + 1 >= cap) {
            cap *= 2;
            char *next = (char *)realloc(buf, cap);
            if (!next) {
                free(buf);
                die("out of memory");
            }
            buf = next;
        }
        buf[len++] = (char)ch;
    }
    buf[len] = '\0';
    return buf;
}

static char *run_child_capture(char **argv) {
    int pipefd[2];
    if (pipe(pipefd) != 0) {
        die("cannot create pipe");
    }

    pid_t pid = fork();
    if (pid < 0) {
        die("cannot fork");
    }

    if (pid == 0) {
        close(pipefd[0]);
        if (dup2(pipefd[1], STDOUT_FILENO) < 0) {
            _exit(126);
        }
        close(pipefd[1]);
        execvp(argv[0], argv);
        _exit(127);
    }

    close(pipefd[1]);
    FILE *fp = fdopen(pipefd[0], "r");
    if (!fp) {
        close(pipefd[0]);
        die("cannot open pipe stream");
    }

    char *output = read_all(fp);
    fclose(fp);

    int status = 0;
    while (waitpid(pid, &status, 0) < 0) {
        if (errno != EINTR) {
            die("cannot wait for child");
        }
    }

    return output;
}

static char *strip_html(const char *input) {
    size_t len = strlen(input);
    char *out = (char *)malloc(len + 1);
    if (!out) {
        die("out of memory");
    }

    int in_tag = 0;
    size_t j = 0;
    for (size_t i = 0; i < len; ++i) {
        char c = input[i];
        if (c == '<') {
            in_tag = 1;
            if (j > 0 && out[j - 1] != '\n') {
                out[j++] = '\n';
            }
            continue;
        }
        if (c == '>') {
            in_tag = 0;
            continue;
        }
        if (!in_tag) {
            out[j++] = c;
        }
    }
    out[j] = '\0';
    return out;
}

static void draw_lines(Display *display, Window window, GC gc, XFontStruct *font, const char *text) {
    int y = 36;
    int line_height = font ? font->ascent + font->descent + 6 : 18;
    const char *start = text;
    while (*start) {
        const char *end = strchr(start, '\n');
        int len = end ? (int)(end - start) : (int)strlen(start);
        if (len > 0) {
            XDrawString(display, window, gc, 20, y, start, len);
        }
        y += line_height;
        if (!end) {
            break;
        }
        start = end + 1;
    }
}

int main(int argc, char **argv) {
    if (argc < 2) {
        fprintf(stderr, "Usage: jx-window-x11 <compiled-jx-executable> [args...]\n");
        return 1;
    }

    char *html = run_child_capture(&argv[1]);
    char *text = strip_html(html);
    free(html);

    Display *display = XOpenDisplay(NULL);
    if (!display) {
        fprintf(stderr, "jx-window-x11: cannot open X display\n");
        free(text);
        return 1;
    }

    int screen = DefaultScreen(display);
    unsigned long white = WhitePixel(display, screen);
    unsigned long black = BlackPixel(display, screen);
    Window root = RootWindow(display, screen);
    Window window = XCreateSimpleWindow(display, root, 80, 80, 900, 620, 1, black, white);
    XStoreName(display, window, "JX X11 Window");
    XSelectInput(display, window, ExposureMask | KeyPressMask | StructureNotifyMask);

    GC gc = XCreateGC(display, window, 0, NULL);
    XFontStruct *font = XLoadQueryFont(display, "fixed");
    if (font) {
        XSetFont(display, gc, font->fid);
    }
    XSetForeground(display, gc, black);
    XMapWindow(display, window);

    for (;;) {
        XEvent event;
        XNextEvent(display, &event);
        if (event.type == Expose) {
            draw_lines(display, window, gc, font, text);
        } else if (event.type == KeyPress || event.type == DestroyNotify) {
            break;
        }
    }

    if (font) {
        XFreeFont(display, font);
    }
    XFreeGC(display, gc);
    XDestroyWindow(display, window);
    XCloseDisplay(display);
    free(text);
    return 0;
}
