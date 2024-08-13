import spacy

nlp = spacy.blank("en")

texts = [
    "Customer: H J ASMUSS AND CO LTD",
    "50MM X 6MM S.E. FLAT 300PLUS (AS/NZS 3679.1-300)",
    "Customer: Example Company",
    "150MM X 12MM MS Equal Angle 300PLUS",
    "Supplier: Dalian Steelforce Hi-Tech Co., Ltd.",
    "100MM X 100MM X 5.0 NOPC SHS C350L0",
    "75MM X 10MM H.R. FLATBAR 400PLUS",
    "Global Corp. International",
    "Steel Dynamics LLC",
    "200MM X 25MM HOT ROLLED FLAT 500PLUS",
    "Certificate No.: 9876-ABCD-1234-EFGH",
    "New Age Metals Ltd.",
    "125MM X 5MM SHEARED EDGE FLATBAR 300PLUS",
    "C 0.22%",
    "Mn 1.05%",
    "Si 0.35%",
    "P 0.015%",
    "S 0.020%",
    "YS 355 MPa",
    "UTS 620 MPa",
    "ELONGN 25%",
    "Certificate No.: 20230405-XYZ123",
    "Customer: Smith & Co. Engineering",
    "Supplier: United Metals Ltd.",
    "YS 450 MPa",
    "UTS 600 MPa",
    "ELONGN 27%"
]

# Process each text and print tokenization results
for text in texts:
    doc = nlp(text)
    print(f"Text: {text}")
    for token in doc:
        print(f"Token: {token.text}, Start: {token.idx}, End: {token.idx + len(token.text)}")
    print("-" * 50)
