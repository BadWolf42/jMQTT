from pydantic import BaseModel


class TestRequest(BaseModel):
    payload: str
    filter: str


class TestResult(BaseModel):
    success: bool = False
    match: bool = False
    value: str
