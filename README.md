# LimeSurvey LSTelegramNotify Plugin

The Limesurvey LSTelegramNotify plugin allows you to send after Survey event to Telegram.

# What does it do?
Telegram offers the feature of bots. A bot allows automated systems and servers to send telegram messages to users. Quite often it can be useful to send stuff to yourself. A classic application of this would be receiving results of cronjob tasks via email. Or maybe you want to grab a small file from your server, but downloading it via SCP would be too much work or wouldn't work at all because firewall stuff / filters / proxy servers / whatever.

## LSTelegramNotify plugin allows you to send after Survey event to Telegram.

Examples
# Send a message to yourself, using a bot token and a chat_id.
telegram -t 123456:AbcDefGhi-JklMnoPrw -c 12345 "Hello, World."

# You can define the token and chat_id in environment variables or config files.
# Then you can just use
telegram "Hello, World."

# Split them into multiple lines
telegram "Hello,"$'\n'"World."
# or
echo -e "Hello\nWorld." | telegram -

# Or you send this one message to another chat:
telegram -c 6789 "Hello, Mars."

# You can also send messages to multiple chats:
telegram -c 1234 -c 6789 "Hello, Planets."

# Send stuff via stdin. It will automatically be sent as monospace code:
ls -l | telegram -

# Use markdown in your message (HTML is available as well):
telegram -M "To *boldly* go, where _no man_ has gone before."

# Send a local file.
telegram -f results.txt "Here are the results."

# Or an image, giving you a preview and stuff.
telegram -i solar_system.png # We don't need to send a message if we're
# sending a file.

# Use environment variables to tell curl to use a proxy server:
HTTPS_PROXY="socks5://127.0.0.1:1234" telegram "Hello, World."
# Check the curl documentation for more info about supported proxy
# protocols.
Requirements
Only bash and curl. Listing known chats with -l requires jq, but you can easily use this tool without this.

In a file /etc/telegram.sh.conf.
In a file ~/.telegram.sh.
In environment variables TELEGRAM_TOKEN and TELEGRAM_CHAT.
As seen above as parameters.
Later variants overwrite earlier variants, so you could define token and chat in /etc/telegram.sh.conf and then overwrite the token with your own in ~/.telegram.sh or on the command line.

The files should look like this:

TELEGRAM_TOKEN="123456:AbcDefGhi-JlkMno"
TELEGRAM_CHAT="12345678"
Please be aware that you should keep your token a secret.

You can also add permanent proxy settings in there by adding:

export HTTPS_PROXY="socks5://127.0.0.1:1234"
See the curl documentation for more information about which proxy protocols are supported.

## Plugin Installation

- Copy the LSTelegramNotify folder to the Limesurvey "plugins" directory.
- Activate the plugin at the Limesurvey plugin manager (requires proper user rights for accessing the feature at the Limesurvey admin interface).
- Configure the plugin at the settings page
