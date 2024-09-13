from functools import lru_cache
from jsonpath_ng import JSONPath, parse, Root
from jsonpath_ng.exceptions import JsonPathParserError
from weakref import WeakValueDictionary


# from logging import getLogger
# logger = getLogger('jmqtt.jsonpath')


class JsonPathDidNotMatch(Exception):
    """Exception to avoid sending a value if jsonPath did not match"""

    pass


class BadJsonPath(Exception):
    """Exception to signal a bad jsonPath"""

    pass


class JsonPathError(JSONPath):
    """JSONPath node used to raise exception with context"""

    def __init__(self, error):
        self.error = error

    def find(self, data):
        raise BadJsonPath(self.error)

    def __str__(self):
        return '$'

    def __repr__(self):
        return f'JsonPathError({self.error})'

    def __eq__(self, other):
        return isinstance(other, JsonPathError) and self.error == other.error

    def __hash__(self):
        return hash(repr(self))


# weak dict to store mapping between jsonPath hash and jsonPath objects
__parserRefs: WeakValueDictionary[str, JSONPath] = WeakValueDictionary()


@lru_cache(maxsize=1024)
def compiledJsonPath(jsonPath: str) -> JSONPath:
    """Cached function returning JSONPath objects associated with jsonPath string"""
    # no jsonPath is equivalent to the whold json -> Root()
    if jsonPath == '':
        expr = Root()
    else:
        try:
            expr = parse(jsonPath)
            # logger.info('Compiled and cached jsonPath "%s"', jsonPath)
        except JsonPathParserError as e:
            expr = JsonPathError(f'invalid {jsonPath=}: {e}')
        except Exception as e:
            expr = JsonPathError(f'other {jsonPath=} compilation error: {e}')
    h = hash(expr)
    if h in __parserRefs:
        return __parserRefs[h]
    __parserRefs[h] = expr
    return expr
