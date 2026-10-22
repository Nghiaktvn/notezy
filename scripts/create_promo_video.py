"""Render a 20-second Notezy motion reel from project-owned 3D artwork."""
from pathlib import Path
import subprocess
import tempfile
import imageio_ffmpeg
from PIL import Image

ROOT = Path(__file__).resolve().parents[1]
FRAMES = [
    ROOT / "assets/notezy-magic-hero-2026.png",
    ROOT / "assets/notezy-study-3d.webp",
    ROOT / "assets/notezy-planner-3d.webp",
    ROOT / "assets/notezy-journal-3d.webp",
]
OUTPUTS = [ROOT / "assets/notezy-study-loop-20s.mp4", ROOT / "frontend/public/assets/notezy-study-loop-20s.mp4"]
FFMPEG = imageio_ffmpeg.get_ffmpeg_exe()

with tempfile.TemporaryDirectory() as temporary:
    clips = []
    for index, picture in enumerate(FRAMES):
        clip = Path(temporary) / f"scene-{index}.mp4"
        prepared = Path(temporary) / f"scene-{index}.png"
        with Image.open(picture) as source:
            background = Image.new("RGBA", source.size, "#fff7e9")
            background.alpha_composite(source.convert("RGBA"))
            background.convert("RGB").save(prepared)
        direction = "zoom+0.0009" if index % 2 == 0 else "max(1.12-0.0009*on,1)"
        video_filter = (
            "scale=2400:1350:force_original_aspect_ratio=increase,crop=2400:1350,"
            f"zoompan=z='{direction}':x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)':d=125:s=1920x1080:fps=25,"
            "format=yuv420p"
        )
        if index:
            video_filter += ",fade=t=in:st=0:d=0.3:color=white"
        if index < len(FRAMES) - 1:
            video_filter += ",fade=t=out:st=4.7:d=0.3:color=white"
        subprocess.run([FFMPEG, "-y", "-loop", "1", "-i", str(prepared), "-vf", video_filter,
                        "-frames:v", "125", "-c:v", "libx264", "-preset", "fast", "-crf", "20",
                        "-pix_fmt", "yuv420p", "-an", str(clip)], check=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        clips.append(clip)
    list_file = Path(temporary) / "clips.txt"
    list_file.write_text("".join(f"file '{clip.as_posix()}'\n" for clip in clips), encoding="utf-8")
    subprocess.run([FFMPEG, "-y", "-f", "concat", "-safe", "0", "-i", str(list_file),
                    "-c", "copy", str(OUTPUTS[0])], check=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
OUTPUTS[1].write_bytes(OUTPUTS[0].read_bytes())
print(f"Rendered {OUTPUTS[0]}")
