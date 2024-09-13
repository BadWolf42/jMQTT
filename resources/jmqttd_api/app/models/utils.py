def strToBool(v):
    if isinstance(v, str):
        return v == '1'  # or v.lower() == 'enable'
    return v


def strToInt(v):
    if isinstance(v, str):
        if v == '':
            return 0
        try:
            return int(v)
        except Exception:
            return v
    return v
