# Labelle installation and desktop integration

This integration opens a downloaded `.labelle` file by passing its contents to
`labelle --batch` on standard input.

## Install Labelle

Labelle is not easy to install manually. On Debian or Ubuntu, this installer
installs `pipx`, installs Labelle with it, and then runs `labelle`:

```console
./opt/labelle-open/install-labelle.sh
```

If Labelle prints an error asking you to create a udev rule, follow the IDs and
instructions in that error. 


After installing the rule, turn the DYMO printer off and back on again. 

Then open the Labelle GUI and confirm that the printer is listed:

```console
labelle-gui
```

## Install the desktop integration

Once Labelle is installed and `labelle` is available in `PATH`, run:

```console
./opt/labelle-open/install-labelle-open.sh
```

The installer copies the wrapper to `/opt/labelle-open`, installs the desktop
entry and MIME definition under `/usr/local/share`, refreshes the desktop
databases, and sets Labelle as the current user's default application for
`application/x-labelle`. The wrapper uses `notify-send` to show Labelle errors
without opening a terminal. On Debian or Ubuntu, install it with:

```console
sudo apt-get install libnotify-bin
```

Test the association with an existing file:

```console
xdg-mime query filetype /path/to/file.labelle
xdg-mime query default application/x-labelle
xdg-open /path/to/file.labelle
```

The expected query results are `application/x-labelle` and `labelle.desktop`.

## Troubleshooting

First, run the installed wrapper from a terminal with a known `.labelle` file:

```console
/opt/labelle-open/labelle-open /path/to/file.labelle
```

This displays errors that may be hidden when the desktop entry uses
`Terminal=false`. It also distinguishes a Labelle or printer problem from a
browser or desktop file-association problem.

Check that the desktop environment recognizes the file and its handler:

```console
xdg-mime query filetype /path/to/file.labelle
xdg-mime query default application/x-labelle
```

The expected results are `application/x-labelle` and `labelle.desktop`. If they
are different, run `install-labelle-open.sh` again as your normal desktop user.

The wrapper records Labelle failures in the system journal with the
`labelle-open` tag. Open a terminal immediately after reproducing the problem
and run:

```console
journalctl --since "10 minutes ago" -t labelle-open
```

To watch the journal while opening a `.labelle` file, run:

```console
journalctl --follow -t labelle-open
```

Labelle may write an `Error:` message to standard error while still exiting
with status `0`. The wrapper treats either an `Error:` message or a nonzero exit
status as a failure and shows a desktop notification.

If no relevant entry appears, use the direct wrapper command above. Also
confirm that Labelle and the notification client are available to desktop
applications:

```console
command -v labelle
command -v notify-send
labelle-gui
```

If `labelle-gui` cannot see the printer, follow any udev instructions printed by
Labelle, reload the rules as instructed, and turn the printer off and back on.
