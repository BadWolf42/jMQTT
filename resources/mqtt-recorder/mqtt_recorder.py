"""MQTT recorder"""

import argparse
import asyncio
import base64
import json
import logging
import sys
import time

import aiomqtt


async def mqtt_record(
    hostname: str,
    output_filename: str | None = None,
    topic: str = "#",
) -> None:
    """Record MQTT messages"""

    with open(output_filename, "w") if output_filename else sys.stdout as output_file:
        async with aiomqtt.Client(hostname) as client:
            await client.subscribe(topic)
            async for message in client.messages:
                record: dict[str, str | float] = {
                    "time": time.time(),
                    "qos": message.qos,
                    "retain": message.retain,
                    "topic": str(message.topic),
                    "msg_b64": base64.urlsafe_b64encode(message.payload).decode(),  # type: ignore
                }
                logging.info("Received: %s", json.dumps(record))
                print(json.dumps(record), file=output_file)
                output_file.flush()


async def mqtt_replay(
    hostname: str,
    input_filename: str | None = None,
    delay: int = 0,
    realtime: bool = False,
    scale: float = 1,
) -> None:
    """Replay MQTT messages"""

    with open(input_filename) if input_filename else sys.stdin as input_file:
        async with aiomqtt.Client(hostname) as client:
            static_delay_s = delay / 1000 if delay > 0 else 0
            last_timestamp = None

            for line in input_file:
                record = json.loads(line)
                logging.debug("%s", record)

                if "msg_b64" in record:
                    msg = base64.urlsafe_b64decode(record["msg_b64"].encode())
                elif "msg" in record:
                    msg = record["msg"].encode()
                else:
                    logging.warning("Missing message attribute: %s", record)
                    continue

                logging.info("Publish: %s", record)
                await client.publish(
                    record["topic"],
                    msg,
                    retain=record.get("retain"),
                    qos=record.get("qos", 0),
                )

                delay_s = static_delay_s

                if realtime or scale != 1:
                    delay_s += (
                        record["time"] - last_timestamp if last_timestamp else 0
                    ) * scale
                    last_timestamp = record["time"]
                if delay_s > 0:
                    logging.debug("Sleeping %.3f seconds", delay_s)
                    await asyncio.sleep(delay_s)


def main():
    """Main function"""
    parser = argparse.ArgumentParser(description="MQTT recorder")

    parser.add_argument(
        "--server",
        dest="server",
        metavar="server",
        help="MQTT broker",
        default="127.0.0.1",
    )
    parser.add_argument(
        "--mode",
        dest="mode",
        metavar="mode",
        choices=["record", "replay"],
        help="Mode of operation (record/replay)",
        default="record",
    )
    parser.add_argument("--input", dest="input", metavar="filename", help="Input file")
    parser.add_argument(
        "--output", dest="output", metavar="filename", help="Output file"
    )
    parser.add_argument(
        "--realtime",
        dest="realtime",
        action="store_true",
        help="Enable realtime replay",
    )
    parser.add_argument(
        "--speed",
        dest="speed",
        type=float,
        default=1,
        metavar="factor",
        help="Realtime speed factor for replay (10=10x)",
    )
    parser.add_argument(
        "--delay",
        dest="delay",
        type=int,
        default=0,
        metavar="milliseconds",
        help="Delay between replayed events",
    )
    parser.add_argument(
        "--debug", dest="debug", action="store_true", help="Enable debugging"
    )

    args = parser.parse_args()

    if args.debug:
        logging.basicConfig(level=logging.DEBUG)
    else:
        logging.basicConfig(level=logging.INFO)

    if args.mode == "replay":
        process = mqtt_replay(
            hostname=args.server,
            input_filename=args.input,
            delay=args.delay,
            realtime=args.realtime,
            scale=1 / args.speed,
        )
    else:
        process = mqtt_record(
            hostname=args.server,
            output_filename=args.output,
        )

    asyncio.run(process)


if __name__ == "__main__":
    main()
