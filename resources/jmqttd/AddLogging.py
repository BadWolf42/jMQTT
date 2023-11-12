import logging


def add_new_loglevels():
    # Add 2 new levels to logging module
    logging.VERBOSE = logging.DEBUG-5
    logging.NONE = logging.CRITICAL+5

    logging.addLevelName(logging.VERBOSE, "VERBOSE")
    logging.addLevelName(logging.NONE, "NONE")

    def verbose(self, message, *args, **kws):
        if self.isEnabledFor(logging.VERBOSE):
            self._log(logging.VERBOSE, message, args, **kws)

    logging.Logger.verbose = verbose
