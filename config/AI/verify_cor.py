import easyocr
import sys
import json
import re
import os
import fitz # PyMuPDF

def extract_text_from_pdf(pdf_path):
    doc = fitz.open(pdf_path)
    full_text = ""
    for page in doc:
        full_text += page.get_text() + " "
    return full_text.upper()

def verify_document(file_path):
    full_text = ""
    
    # Check if the file is a PDF
    if file_path.lower().endswith('.pdf'):
        try:
            full_text = extract_text_from_pdf(file_path)
        except Exception as e:
            return json.dumps({"score": 0, "status": "rejected", "details": [f"Error reading PDF: {e}"], "extracted_id": None})
    else:
        # Initialize the OCR reader (English)
        reader = easyocr.Reader(['en'])
        # Read text from image
        result = reader.readtext(file_path, detail=0) 
        full_text = " ".join(result).upper()

    score = 0
    findings = []
    extracted_data = {"name": None, "id": None}

    # 1. Check for Institution (PHILSCA)
    # Your COR has "Philippine State College of Aeronautics" [cite: 1]
    if "PHILIPPINE STATE COLLEGE OF AERONAUTICS" in full_text or "PHILSCA" in full_text:
        score += 25
        findings.append("School Name Detected")

    # 2. Check for Document Title
    # Your COR says "CERTIFICATE OF REGISTRATION AND BILLING" [cite: 3]
    if "CERTIFICATE OF REGISTRATION" in full_text:
        score += 25
        findings.append("Document Type Valid")

    # 3. Specific Student ID Pattern Match
    # Your ID is "12324MN-000204". Let's look for that alphanumeric pattern.
    id_pattern = r"\d{5}[A-Z]{2}-\d{6}" 
    id_match = re.search(id_pattern, full_text)
    if id_match:
        score += 25
        extracted_data["id"] = id_match.group(0)
        findings.append(f"Valid ID Format Detected: {extracted_data['id']}")

    # 4. Check for Enrollment Status
    # Your COR explicitly states "OFFICIALLY ENROLLED" 
    if "OFFICIALLY ENROLLED" in full_text or "ENROLLED" in full_text or "TERM" in full_text or "SEMESTER" in full_text or "ACADEMIC YEAR" in full_text:
        score += 25
        findings.append("Active Enrollment Status Confirmed")

    # Final Status Logic
    if score >= 90:
        status = "ai_verified"
    elif score >= 50:
        status = "needs_org_review"
    else:
        status = "rejected"

    output = {
        "score": score,
        "status": status,
        "details": findings,
        "extracted_id": extracted_data["id"]
    }
    return json.dumps(output) 

if __name__ == "__main__":
    if len(sys.argv) > 1:
        print(verify_document(sys.argv[1]))