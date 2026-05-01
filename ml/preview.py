import pandas as pd
df = pd.read_csv('https://raw.githubusercontent.com/angelpatriciads/credit-card-risk-classification/main/credit_risk_dataset.csv')
with open('data_preview.txt', 'w') as f:
    f.write(",".join(df.columns.tolist()) + "\n")
    for i in range(5):
        f.write(",".join([str(x) for x in df.iloc[i].tolist()]) + "\n")
