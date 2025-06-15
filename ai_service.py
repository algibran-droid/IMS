from fastapi import FastAPI, Query, HTTPException, Body
from pydantic import BaseModel
import mysql.connector
from mysql.connector import Error
import uvicorn
import subprocess
import os
import requests
from transformers import pipeline
from typing import List, Optional

app = FastAPI()

# ----------------------------------------
# ROOT DIRECTORY UNTUK FILE PHP-MU
root_dir = r"C:\xampp\htdocs\project-ku"  # <-- Ganti sesuai folder PHP-mu
# ----------------------------------------

# Ganti dengan API Key dan Search Engine ID milikmu
GOOGLE_API_KEY = "AIzaSyDCpJKq3yBLJFOSu_YnK-ILw6yDT0hxUAo"
GOOGLE_CX      = "d142e411e548a4fa3"

def get_db_connection():
    try:
        conn = mysql.connector.connect(
            host="localhost",
            user="root",
            password="",     # Sesuaikan password MySQL kamu
            database="error" # Database untuk solusi error
        )
        return conn
    except Error as e:
        print("Database connection error:", e)
        return None

def search_google(query_text: str):
    """
    Cari solusi lewat Google Custom Search API.
    Kembalikan snippet pertama jika ada.
    """
    url = (
        f"https://www.googleapis.com/customsearch/v1"
        f"?key={GOOGLE_API_KEY}&cx={GOOGLE_CX}"
        f"&q={requests.utils.quote(query_text)}"
    )
    try:
        resp = requests.get(url, timeout=5)
        resp.raise_for_status()
        items = resp.json().get("items", [])
        if items:
            return items[0].get("snippet")
    except Exception as e:
        print("Google search error:", e)
    return None

@app.get("/analyze_error")
def analyze_error(
    error_code: str = Query(...),
    error_message: str = Query(...)
):
    conn = get_db_connection()
    if not conn:
        raise HTTPException(500, "Database connection error")
    cur = conn.cursor(dictionary=True)
    cur.execute("SELECT solution FROM solutions WHERE error_code = %s", (error_code,))
    row = cur.fetchone()
    if row and row.get("solution"):
        solution = row["solution"]
    else:
        # 1) ML text generation
        solution_ml = None
        try:
            gen = pipeline("text-generation", model="distilgpt2")
            prompt = (
                f"Error Code: {error_code}. "
                f"Error Message: {error_message}. "
                "Berikan solusi detail untuk memperbaiki error ini."
            )
            out = gen(prompt, max_length=150, do_sample=True)
            solution_ml = out[0]["generated_text"].strip()
        except:
            pass
        # 2) Fallback Google
        solution_google = None
        if not solution_ml:
            solution_google = search_google(error_message)
        # Pilih
        if solution_ml:
            solution = solution_ml
        elif solution_google:
            solution = solution_google
        else:
            solution = "Error belum diketahui, silakan tambahkan solusi."
        # Simpan
        cur.execute(
            "INSERT IGNORE INTO solutions (error_code, error_message, solution, created_at) "
            "VALUES (%s,%s,%s,NOW())",
            (error_code, error_message, solution)
        )
        conn.commit()
    cur.close()
    conn.close()
    return {"solution": solution}

class UpdateSolution(BaseModel):
    error_code: str
    solution: str

@app.post("/update_solution")
def update_solution(update: UpdateSolution):
    conn = get_db_connection()
    if not conn:
        raise HTTPException(500, "Database connection error")
    cur = conn.cursor()
    cur.execute(
        "INSERT INTO solutions (error_code,solution) VALUES (%s,%s) "
        "ON DUPLICATE KEY UPDATE solution=%s",
        (update.error_code, update.solution, update.solution)
    )
    conn.commit()
    cur.close()
    conn.close()
    return {
        "status": "Solusi diperbarui",
        "error_code": update.error_code,
        "solution": update.solution
    }

@app.get("/chatbot")
def chatbot(question: str = Query(...)):
    conn = get_db_connection()
    if not conn:
        raise HTTPException(500, "Database connection error")
    cur = conn.cursor(dictionary=True)
    cur.execute("SELECT solution FROM solutions WHERE error_code=%s", (question,))
    row = cur.fetchone()
    cur.close()
    conn.close()
    return {"answer": row["solution"]} if row and row.get("solution") else {"answer": "Maaf, solusi belum ditemukan!"}

class CodeAnalysisRequest(BaseModel):
    code: str

@app.post("/analyze_code")
def analyze_code(request: CodeAnalysisRequest):
    tmp = "temp_code.php"
    try:
        with open(tmp, "w", encoding="utf-8") as f:
            f.write(request.code)
        proc = subprocess.run(["php","-l",tmp], capture_output=True, text=True, timeout=10)
        out = proc.stdout.strip() or proc.stderr.strip()
        os.remove(tmp)
        if "No syntax errors detected" in out:
            return {"status":"OK","message":"Tidak ditemukan kesalahan sintaksis."}
        return {"status":"Error","message":out}
    except subprocess.TimeoutExpired:
        if os.path.exists(tmp): os.remove(tmp)
        raise HTTPException(500, "Proses analisis kode memakan waktu terlalu lama.")
    except Exception as e:
        if os.path.exists(tmp): os.remove(tmp)
        raise HTTPException(500, str(e))

# ————— Endpoint baru: cek banyak file PHP —————

@app.post("/check_multiple_files")
def check_multiple_files(
    file_paths: Optional[List[str]] = Body(None)
):
    """
    Jika client kirim file_paths: gunakan itu.
    Jika tidak: scan semua .php di root_dir.
    """
    # 1) Bangun list file
    if not file_paths:
        file_paths = []
        for dp, _, files in os.walk(root_dir):
            for fn in files:
                if fn.lower().endswith(".php"):
                    file_paths.append(os.path.join(dp, fn))

    results = []
    for path in file_paths:
        if not os.path.exists(path):
            results.append({"file": path, "status": "File tidak ditemukan"})
            continue
        try:
            proc = subprocess.run(["php","-l",path], capture_output=True, text=True, timeout=10)
            out = proc.stdout.strip() or proc.stderr.strip()
            if "No syntax errors detected" in out:
                results.append({"file":path,"status":"OK","message":"Tidak ada kesalahan sintaksis"})
            else:
                # nama file jadi error_code
                code = os.path.basename(path)
                # cari solusi di DB
                sugg = None
                conn = get_db_connection()
                if conn:
                    cur = conn.cursor(dictionary=True)
                    cur.execute("SELECT solution FROM solutions WHERE error_code=%s",(code,))
                    r = cur.fetchone()
                    cur.close()
                    conn.close()
                    if r and r.get("solution"):
                        sugg = r["solution"]
                # fallback Google
                if not sugg:
                    sugg = search_google(out) or "Belum ditemukan solusi."
                results.append({
                    "file": path,
                    "status": "Error",
                    "message": out,
                    "suggested_solution": sugg
                })
        except subprocess.TimeoutExpired:
            results.append({"file":path,"status":"Error","message":"Analisis timeout"})
        except Exception as e:
            results.append({"file":path,"status":"Error","message":str(e)})
    return {"results": results}

if __name__ == "__main__":
    uvicorn.run(app, host="0.0.0.0", port=5000)
