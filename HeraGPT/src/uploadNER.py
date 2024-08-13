import spacy
from spacy.tokens import DocBin
from spacy.training.example import Example
import os

# Load a blank SpaCy model
nlp = spacy.blank("en")

# Set the path for the uploads folder
UPLOAD_FOLDER = "uploads"
if not os.path.exists(UPLOAD_FOLDER):
    os.makedirs(UPLOAD_FOLDER)

# Create a function to align entities
def align_entities(nlp, text, entities):
    doc = nlp.make_doc(text)
    aligned_entities = []
    for start, end, label in entities:
        span = doc.char_span(start, end, label=label)
        if span is not None:
            aligned_entities.append((span.start_char, span.end_char, label))
        else:
            print(f"Skipping misaligned entity: '{text[start:end]}' in '{text}'")
    return aligned_entities

# Example training data with manual alignment
TRAIN_DATA = [
    ("Certificate No.: 030065", {"entities": [(16, 22, "CERTIFICATE_NUMBER")]}),
    ("Customer: H J ASMUSS AND CO LTD", {"entities": [(10, 36, "CUSTOMER")]}),
    ("Supplier: INFRABUILD STEEL", {"entities": [(10, 28, "SUPPLIER")]}),
    ("40MM X 40MM X 5MM ANGLE 300PLUS", {"entities": [(0, 21, "PRODUCT"), (22, 29, "GRADE")]}),
    ("50MM X 6MM S.E. FLAT 300PLUS", {"entities": [(0, 19, "PRODUCT"), (20, 27, "GRADE")]}),
    ("YS 365 MPa", {"entities": [(0, 2, "PROPERTY"), (3, 6, "VALUE"), (7, 10, "UNIT")]}),
    ("UTS 520 MPa", {"entities": [(0, 3, "PROPERTY"), (4, 7, "VALUE"), (8, 11, "UNIT")]}),
    ("ELONGN 39%", {"entities": [(0, 6, "PROPERTY"), (7, 9, "VALUE"), (10, 11, "UNIT")]}),
    ("Mn 0.75%", {"entities": [(0, 2, "ELEMENT"), (3, 7, "PERCENTAGE")]}),
    ("C 0.20%", {"entities": [(0, 1, "ELEMENT"), (2, 6, "PERCENTAGE")]}),
    ("P 0.014%", {"entities": [(0, 1, "ELEMENT"), (2, 7, "PERCENTAGE")]}),
    # Additional examples can be added here
]

# Align entities before training
aligned_train_data = []
for text, annotations in TRAIN_DATA:
    entities = annotations['entities']
    aligned_entities = align_entities(nlp, text, entities)
    aligned_train_data.append((text, {"entities": aligned_entities}))

# Add the NER pipeline
ner = nlp.add_pipe("ner")

# Add labels to the NER
for _, annotations in aligned_train_data:
    for ent in annotations.get("entities"):
        ner.add_label(ent[2])

# Convert training data to SpaCy's format
db = DocBin()
for text, annotations in aligned_train_data:
    doc = nlp.make_doc(text)
    example = Example.from_dict(doc, annotations)
    db.add(example.reference)

# Save the training data to the uploads folder
train_data_path = os.path.join(UPLOAD_FOLDER, "train.spacy")
db.to_disk(train_data_path)

# Train the model
optimizer = nlp.begin_training()

for i in range(10):  # 10 iterations
    losses = {}
    batches = spacy.util.minibatch(aligned_train_data, size=2)
    for batch in batches:
        texts, annotations = zip(*batch)
        examples = [Example.from_dict(nlp.make_doc(text), ann) for text, ann in zip(texts, annotations)]
        nlp.update(examples, drop=0.5, losses=losses)
    print(f"Iteration {i+1} - Losses: {losses}")

# Save the trained model
model_path = os.path.join(UPLOAD_FOLDER, "ner_model")
nlp.to_disk(model_path)

# Verify tokenization and entity alignment
for text, annotations in aligned_train_data:
    doc = nlp(text)
    print(f"Text: {text}")
    for ent in doc.ents:
        print(f"Entity: '{ent.text}', Label: {ent.label_}")
    print("--------------------------------------------------")
